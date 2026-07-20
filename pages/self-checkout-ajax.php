<?php declare(strict_types=1);

header('Content-Type: application/json');

try {
    // CSRF check
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        throw new \Exception('Session expired. Please refresh and try again.');
    }

    // Rate limiting: max 5 requests per 60 seconds (DB-based, not session-based)
    $rateIp = getClientIp();
    $remaining = rate_limit_check_sliding($db, 'sc:' . $rateIp, 'self_checkout', 5, 60);
    if ($remaining === 0) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait before trying again.']);
        exit;
    }
    rate_limit_hit($db, 'sc:' . $rateIp, 'self_checkout');

    $data = json_decode($_POST['cart'] ?? '[]', true);
    if (empty($data)) {
        throw new \Exception('Cart is empty.');
    }

    // Enforce max quantity per item and max total
    $maxQtyPerItem = 99;
    $maxTotal = 5000;

    $paymentMethod = $_POST['payment_method'] ?? 'card';
    $cardLast4 = substr(trim($_POST['card_last4'] ?? ''), 0, 4);
    $cashAmount = (float) ($_POST['cash_amount'] ?? 0);
    $cardAmount = (float) ($_POST['card_amount'] ?? 0);
    $changeAmount = (float) ($_POST['change_amount'] ?? 0);

    if ($paymentMethod === 'card' && !preg_match('/^\d{4}$/', $cardLast4)) {
        throw new \Exception('Invalid card number.');
    }
    if ($paymentMethod === 'cash' && $cashAmount <= 0) {
        throw new \Exception('Invalid cash amount.');
    }

    $customerName = trim($_POST['customer_name'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $photoConsent = ($_POST['photo_consent'] ?? '') === '1';
    $photoPayload = null;
    $photoData = $_POST['photo'] ?? '';

    // Never retain a photo merely because a client submitted one. Consent must
    // be explicit and the image must be valid before the sale is created.
    if ($photoConsent && $photoData !== '') {
        if (!preg_match('/^data:image\/(jpeg|png|jpg);base64,/', $photoData)) {
            throw new \Exception('Invalid customer photo. Please retake it or continue without a photo.');
        }
        $base64 = substr($photoData, strpos($photoData, ',') + 1);
        $decoded = base64_decode($base64, true);
        if ($decoded === false || strlen($decoded) > 2 * 1024 * 1024) {
            throw new \Exception('Customer photo must be a valid image smaller than 2 MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $decoded);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new \Exception('Customer photo must be a JPEG or PNG image.');
        }
        $photoPayload = ['bytes' => $decoded, 'extension' => $mime === 'image/png' ? 'png' : 'jpg'];
    }

    // Determine cashier
    if (isLoggedIn()) {
        $cashierId = (int) ($_SESSION['user_id'] ?? 0);
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = 'selfcheckout'");
        $stmt->execute();
        $scUser = $stmt->fetch();
        $cashierId = $scUser ? (int) $scUser['id'] : 0;
        if (!$cashierId) {
            $db->exec("INSERT IGNORE INTO users (username, password, full_name, role, status) VALUES ('selfcheckout', '', 'Self-Checkout', 'cashier', 'active')");
            $stmt = $db->query("SELECT id FROM users WHERE username = 'selfcheckout'");
            $scUser = $stmt->fetch();
            $cashierId = $scUser ? (int) $scUser['id'] : 0;
        }
        if (!$cashierId) {
            throw new \Exception('Self-checkout system user not found.');
        }
    }

    $db->beginTransaction();

    // Verify each item against the database
    $calculatedSubtotal = 0;
    $verifiedItems = [];
    $checkStmt = $db->prepare("SELECT id, name, price, cost_price, stock_quantity FROM products WHERE id = ? AND status = 'active' AND store_id = ? FOR UPDATE");

    foreach ($data as $i => $item) {
        $productId = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);

        if ($productId <= 0) {
            throw new \Exception('Invalid product in cart.');
        }
        if ($qty <= 0 || $qty > $maxQtyPerItem) {
            throw new \Exception("Invalid quantity for {$item['name']}.");
        }

        $checkStmt->execute([$productId, activeStoreId()]);
        $dbProduct = $checkStmt->fetch();

        if (!$dbProduct) {
            throw new \Exception("Product '{$item['name']}' not found or is inactive.");
        }

        // Verify price integrity — reject if client price doesn't match DB
        $dbPrice = (float) $dbProduct['price'];
        $clientPrice = (float) ($item['price'] ?? 0);
        if (abs($dbPrice - $clientPrice) > 0.01) {
            throw new \Exception("Price mismatch for {$dbProduct['name']}. Please refresh and try again.");
        }

        // Verify stock
        $dbStock = (int) $dbProduct['stock_quantity'];
        if ($qty > $dbStock) {
            throw new \Exception("Not enough stock for {$dbProduct['name']}. Only {$dbStock} available.");
        }

        $lineTotal = $dbPrice * $qty;
        $calculatedSubtotal += $lineTotal;

        $verifiedItems[] = [
            'product_id' => $productId,
            'product_name' => $dbProduct['name'],
            'price' => $dbPrice,
            'cost_price' => (float) ($dbProduct['cost_price'] ?? 0),
            'qty' => $qty,
            'total' => $lineTotal,
        ];
    }

    // Verify total integrity — recalculate server-side
    $calculatedTax = round($calculatedSubtotal * (TAX_RATE / 100), 2);
    $calculatedTotal = round($calculatedSubtotal + $calculatedTax, 2);

    $clientSubtotal = (float) ($_POST['subtotal'] ?? 0);
    $clientTotal = (float) ($_POST['total'] ?? 0);

    if (abs($calculatedTotal - $clientTotal) > 0.01) {
        throw new \Exception('Total mismatch detected. Please refresh and try again.');
    }

    // Enforce max transaction total
    if ($calculatedTotal > $maxTotal) {
        throw new \Exception("Transaction exceeds maximum of " . CURRENCY . " {$maxTotal}.");
    }

    // Payment verification
    if ($paymentMethod === 'cash' && $cashAmount < $calculatedTotal) {
        throw new \Exception('Insufficient cash amount.');
    }
    if ($paymentMethod === 'mixed' && ($cashAmount + $cardAmount) < $calculatedTotal) {
        throw new \Exception('Insufficient payment amount.');
    }

    $receiptNumber = generateReceiptNumber($db);
    $stmt = $db->prepare("INSERT INTO sales (receipt_number, cashier_id, cashier_name, subtotal, tax, tax_rate, discount, discount_type, total, payment_method, cash_amount, card_amount, change_amount, customer_name, customer_email, customer_phone, store_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $receiptNumber,
        $cashierId,
        'Self Checkout',
        $calculatedSubtotal,
        $calculatedTax,
        TAX_RATE,
        0,
        'percentage',
        $calculatedTotal,
        $paymentMethod,
        $paymentMethod === 'cash' ? $cashAmount : 0,
        $paymentMethod === 'card' ? $calculatedTotal : ($cardAmount ?: 0),
        $changeAmount,
        $customerName ?: null,
        $customerEmail ?: null,
        $customerPhone ?: null,
        activeStoreId()
    ]);
    $saleId = (int) $db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, total) VALUES (?,?,?,?,?,?,?)");
    $updateStock = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ? AND stock_quantity >= ?");
    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?,?,?,?,?,?,?,?,?)");

    foreach ($verifiedItems as $vi) {
        $itemStmt->execute([$saleId, $vi['product_id'], $vi['product_name'], $vi['qty'], $vi['price'], $vi['cost_price'], $vi['total']]);

        $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
        $pStmt->execute([$vi['product_id'], activeStoreId()]);
        $prod = $pStmt->fetch();
        $prevStock = (int) ($prod['stock_quantity'] ?? 0);
        $newStock = $prevStock - $vi['qty'];

        $updateStock->execute([$vi['qty'], $vi['product_id'], activeStoreId(), $vi['qty']]);
        if ($updateStock->rowCount() !== 1) {
            throw new \Exception('Insufficient stock for ' . $vi['product_name'] . '.');
        }
        $adjStmt->execute([$vi['product_id'], $cashierId, 'Self Checkout', 'sale', $vi['qty'], $prevStock, $newStock, 'Self-checkout ' . $receiptNumber, activeStoreId()]);
    }

    $db->commit();

    if ($photoPayload !== null) {
        $photoDir = __DIR__ . '/../captured_photos';
        if (!is_dir($photoDir)) {
            @mkdir($photoDir, 0755, true);
            @file_put_contents($photoDir . '/.htaccess', "Require all denied\n");
        }
        $relativePath = 'captured_photos/sale_' . $saleId . '.' . $photoPayload['extension'];
        $photoFile = __DIR__ . '/../' . $relativePath;
        if (@file_put_contents($photoFile, $photoPayload['bytes'], LOCK_EX) !== false) {
            $photoStmt = $db->prepare("UPDATE sales SET customer_photo_path = ?, customer_photo_consent_at = NOW(), customer_photo_delete_after = DATE_ADD(NOW(), INTERVAL " . CUSTOMER_PHOTO_RETENTION_DAYS . " DAY) WHERE id = ?");
            $photoStmt->execute([$relativePath, $saleId]);
        } else {
            error_log('Unable to save consented customer photo for sale ' . $saleId);
        }
    }
    purgeExpiredCustomerPhotos($db);

    $_SESSION['pos_flash'] = [
        'type' => 'success',
        'message' => "Self-checkout sale completed! Receipt: {$receiptNumber}",
        'receipt_id' => $saleId,
        'receipt_number' => $receiptNumber,
    ];

    echo json_encode(['success' => true, 'sale_id' => $saleId, 'receipt_number' => $receiptNumber]);
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
