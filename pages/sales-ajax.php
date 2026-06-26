<?php

declare(strict_types=1);

header('Content-Type: application/json');

try {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        throw new \Exception('Invalid security token. Please refresh the page.');
    }

    $data = json_decode($_POST['cart'] ?? '[]', true);
    if (empty($data)) {
        throw new \Exception('Cart is empty.');
    }

    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $cashAmount = (float) ($_POST['cash_amount'] ?? 0);
    $cardAmount = (float) ($_POST['card_amount'] ?? 0);
    $changeAmount = (float) ($_POST['change_amount'] ?? 0);
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        throw new \Exception('Session expired. Please login again.');
    }

    $storeId = activeStoreId();

    // Server-side validation: verify each product, recalculate totals
    $serverSubtotal = 0.0;
    $validatedItems = [];

    foreach ($data as $item) {
        $productId = (int) $item['id'];
        $qty = (int) ($item['qty'] ?? 0);

        if ($qty <= 0) {
            throw new \Exception('Invalid quantity for product: ' . ($item['name'] ?? 'Unknown'));
        }

        $pStmt = $db->prepare("SELECT id, name, price, cost_price, stock_quantity FROM products WHERE id = ? AND store_id = ? AND status = 'active'");
        $pStmt->execute([$productId, $storeId]);
        $product = $pStmt->fetch();

        if (!$product) {
            throw new \Exception('Product not found or inactive: ' . ($item['name'] ?? 'Unknown'));
        }

        if ((int) $product['stock_quantity'] < $qty) {
            throw new \Exception('Insufficient stock for ' . $product['name'] . ': only ' . (int) $product['stock_quantity'] . ' available, requested ' . $qty);
        }

        $validatedItems[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'price' => (float) $product['price'],
            'cost_price' => (float) ($product['cost_price'] ?? 0),
            'qty' => $qty,
            'stock_quantity' => (int) $product['stock_quantity'],
        ];

        $serverSubtotal += (float) $product['price'] * $qty;
    }

    // Server-side totals calculation
    $discountPct = min(100, max(0, (float) ($_POST['discount_pct'] ?? 0)));
    $discount = $serverSubtotal * ($discountPct / 100);
    $afterDiscount = $serverSubtotal - $discount;
    $taxRate = TAX_RATE;
    $tax = $afterDiscount * ($taxRate / 100);
    $total = $afterDiscount + $tax;

    // Validate payment amounts
    if ($paymentMethod === 'cash' && $cashAmount < $total) {
        throw new \Exception('Insufficient cash amount.');
    }
    if ($paymentMethod === 'mixed' && ($cashAmount + $cardAmount) < $total) {
        throw new \Exception('Insufficient payment amount.');
    }
    if ($paymentMethod === 'card') {
        $cardAmount = $total;
    }
    if ($paymentMethod === 'cash') {
        $changeAmount = $cashAmount - $total;
    } elseif ($paymentMethod === 'mixed') {
        $changeAmount = $cashAmount - ($total - $cardAmount);
    }

    $db->beginTransaction();

    $receiptNumber = generateReceiptNumber($db);
    $stmt = $db->prepare("INSERT INTO sales (receipt_number, cashier_id, cashier_name, subtotal, tax, tax_rate, discount, discount_type, total, payment_method, cash_amount, card_amount, change_amount, store_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $cashierName = $user['display_name'] ?? $user['full_name'];
    $stmt->execute([$receiptNumber, $user['id'], $cashierName, $serverSubtotal, $tax, $taxRate, $discount, 'percentage', $total, $paymentMethod, $cashAmount, $cardAmount, $changeAmount, $storeId]);
    $saleId = (int) $db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, total) VALUES (?,?,?,?,?,?,?)");
    $updateStock = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ?");
    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?,?,?,?,?,?,?,?,?)");

    foreach ($validatedItems as $item) {
        $lineTotal = $item['price'] * $item['qty'];
        $itemStmt->execute([$saleId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['cost_price'], $lineTotal]);

        $prevStock = $item['stock_quantity'];
        $newStock = $prevStock - $item['qty'];

        $updateStock->execute([$item['qty'], $item['id'], $storeId]);
        $adjStmt->execute([$item['id'], $user['id'], $user['full_name'], 'sale', $item['qty'], $prevStock, $newStock, 'Sale ' . $receiptNumber, $storeId]);
    }

    // Clear the CSRF token to prevent duplicate submission
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));

    $db->commit();

    $_SESSION['pos_flash'] = [
        'type' => 'success',
        'message' => "Sale completed! Receipt: {$receiptNumber}",
        'receipt_id' => $saleId,
        'receipt_number' => $receiptNumber,
    ];

    logAction($db, 'sale_completed', 'sale', $saleId, 'Sale completed: ' . $receiptNumber . ', total: ' . $total . ', store_id: ' . $storeId);

    echo json_encode(['success' => true, 'sale_id' => $saleId, 'receipt_number' => $receiptNumber]);
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
