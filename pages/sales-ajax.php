<?php

declare(strict_types=1);

header('Content-Type: application/json');

try {
    requireAjaxLogin();

    $action = $_POST['action'] ?? '';
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        throw new \Exception('Invalid security token. Please refresh the page.');
    }

    // Handle held sale actions
    if ($action === 'hold_sale') {
        $cartJson = $_POST['cart'] ?? '[]';
        $subtotal = (float) ($_POST['subtotal'] ?? 0);
        $cartData = json_decode($cartJson, true);
        if (empty($cartData)) {
            throw new \Exception('Cart is empty.');
        }
        $heldId = holdSale($db, (int) CURRENT_USER_ID, activeStoreId(), $cartJson, $subtotal);
        echo json_encode(['success' => true, 'held_id' => $heldId]);
        exit;
    }

    if ($action === 'get_held_sale') {
        $heldId = (int) ($_POST['held_id'] ?? 0);
        $held = getHeldSaleById($db, $heldId, (int) CURRENT_USER_ID, activeStoreId());
        if (!$held) {
            throw new \Exception('Held sale not found.');
        }
        $cart = json_decode($held['cart_data'], true);
        echo json_encode(['success' => true, 'cart' => $cart, 'subtotal' => (float) $held['subtotal']]);
        exit;
    }

    if ($action === 'delete_held_sale') {
        $heldId = (int) ($_POST['held_id'] ?? 0);
        deleteHeldSale($db, $heldId, (int) CURRENT_USER_ID, activeStoreId());
        echo json_encode(['success' => true]);
        exit;
    }

    // Main sale completion
    $data = json_decode($_POST['cart'] ?? '[]', true);
    if (empty($data)) {
        throw new \Exception('Cart is empty.');
    }

    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $cashAmount = (float) ($_POST['cash_amount'] ?? 0);
    $cardAmount = (float) ($_POST['card_amount'] ?? 0);
    $changeAmount = (float) ($_POST['change_amount'] ?? 0);

    $storeId = activeStoreId();
    $db->beginTransaction();

    // Server-side validation: verify each product, recalculate totals
    $serverSubtotal = 0.0;
    $validatedItems = [];

    foreach ($data as $item) {
        $productId = (int) $item['id'];
        $qty = (int) ($item['qty'] ?? 0);

        if ($qty <= 0) {
            throw new \Exception('Invalid quantity for product: ' . ($item['name'] ?? 'Unknown'));
        }

        $pStmt = $db->prepare("SELECT id, name, price, cost_price, stock_quantity, category FROM products WHERE id = ? AND store_id = ? AND status = 'active' FOR UPDATE");
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
            'category' => $product['category'] ?? '',
        ];

        $serverSubtotal += (float) $product['price'] * $qty;
    }

    // Server-side totals calculation
    $discountPct = min(100, max(0, (float) ($_POST['discount_pct'] ?? 0)));
    $checkoutSettings = getStoreSettings($db, $storeId);
    $maxDiscount = min(100, max(0, (float) ($checkoutSettings['max_discount_percentage'] ?? 50)));
    if (isCashier()) {
        if (empty($checkoutSettings['cashier_can_apply_discounts']) && $discountPct > 0) {
            throw new \Exception('Cashiers are not permitted to apply manual discounts.');
        }
        $maxDiscount = min($maxDiscount, max(0, (float) ($checkoutSettings['cashier_discount_limit'] ?? 0)));
    }
    if ($discountPct > $maxDiscount) {
        throw new \Exception('The requested discount exceeds your permitted limit.');
    }
    $discount = $serverSubtotal * ($discountPct / 100);

    // Coupon validation (server-side - do not trust the browser)
    $couponId = null;
    $couponCode = null;
    $couponDiscountAmount = 0.0;
    $couponDiscountValue = 0.0;
    $couponDiscountType = 'percentage';
    $subtotalBeforeDiscount = $serverSubtotal;
    $totalAfterDiscount = $serverSubtotal;

    $couponCodeInput = trim($_POST['coupon_code'] ?? '');
    if ($couponCodeInput !== '') {
        $result = validateCoupon($db, $couponCodeInput, $serverSubtotal, $storeId);
        if (!$result['success']) {
            throw new \Exception($result['message']);
        }
        $coupon = $result['coupon'];

        // Build cart items for product/category checks
        $cartForCalc = [];
        foreach ($validatedItems as $vi) {
            $cartForCalc[] = [
                'product_id' => $vi['id'],
                'price' => $vi['price'],
                'qty' => $vi['qty'],
                'category' => $vi['category'],
            ];
        }

        $calcResult = calculateCouponDiscount($coupon, $serverSubtotal, $cartForCalc);
        $couponDiscountAmount = $calcResult['discount_amount'];
        $couponDiscountType = $calcResult['discount_type'];
        $couponDiscountValue = $calcResult['discount_value'];
        $subtotalBeforeDiscount = $calcResult['subtotal_before_discount'];
        $totalAfterDiscount = $calcResult['total_after_discount'];
        $couponId = (int) $coupon['id'];
        $couponCode = $coupon['code'];

        // Use coupon discount instead of manual discount
        $discount = $couponDiscountAmount;
    }

    $afterDiscount = $serverSubtotal - $discount;
    $taxRate = TAX_RATE;
    $tax = $afterDiscount * ($taxRate / 100);
    $total = $afterDiscount + $tax;

    // Prevent negative totals
    if ($total < 0) {
        throw new \Exception('Total cannot be negative.');
    }

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

    $receiptNumber = generateReceiptNumber($db);
    $stmt = $db->prepare("INSERT INTO sales (receipt_number, cashier_id, cashier_name, subtotal, tax, tax_rate, discount, discount_type, total, payment_method, cash_amount, card_amount, change_amount, store_id, discount_coupon_id, discount_code, discount_value, discount_amount, subtotal_before_discount, total_after_discount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $cashierName = userName();
    $stmt->execute([$receiptNumber, $_SESSION['user_id'], $cashierName, $serverSubtotal, $tax, $taxRate, $discount, $couponDiscountType, $total, $paymentMethod, $cashAmount, $cardAmount, $changeAmount, $storeId, $couponId, $couponCode, $couponDiscountValue, $couponDiscountAmount, $subtotalBeforeDiscount, $totalAfterDiscount]);
    $saleId = (int) $db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, total) VALUES (?,?,?,?,?,?,?)");
    $updateStock = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ? AND stock_quantity >= ?");
    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?,?,?,?,?,?,?,?,?)");

    foreach ($validatedItems as $item) {
        $lineTotal = $item['price'] * $item['qty'];
        $itemStmt->execute([$saleId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['cost_price'], $lineTotal]);

        $prevStock = $item['stock_quantity'];
        $newStock = $prevStock - $item['qty'];

        $updateStock->execute([$item['qty'], $item['id'], $storeId, $item['qty']]);
        if ($updateStock->rowCount() !== 1) {
            throw new \Exception('Insufficient stock for ' . $item['name'] . '.');
        }
        $adjStmt->execute([$item['id'], $_SESSION['user_id'], userName(), 'sale', $item['qty'], $prevStock, $newStock, 'Sale ' . $receiptNumber, $storeId]);
    }

    // Increment coupon usage if coupon was applied
    if ($couponId) {
        incrementCouponUsage($db, $couponId);
    }

    // Clear the CSRF token to prevent duplicate submission
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));

    $db->commit();

    $formattedTotal = money($total);
    $formattedDiscount = money($discount);
    $paymentLabel = ucfirst($paymentMethod);
    if ($paymentMethod === 'mixed') {
        $paymentLabel = 'Mixed (Cash ' . money($cashAmount) . ' + Card ' . money($cardAmount) . ')';
    }

    $_SESSION['pos_flash'] = [
        'type' => 'success',
        'message' => "Sale completed! Receipt: {$receiptNumber}",
        'receipt_id' => $saleId,
        'receipt_number' => $receiptNumber,
        'formatted_total' => $formattedTotal,
        'formatted_discount' => $formattedDiscount,
        'discount_code' => $couponCode,
        'payment_method' => $paymentLabel,
    ];

    logAction($db, 'sale_completed', 'sale', $saleId, 'Sale completed: ' . $receiptNumber . ', total: ' . $total . ', discount: ' . $discount . ', coupon: ' . ($couponCode ?: 'none') . ', store_id: ' . $storeId);

    echo json_encode(['success' => true, 'sale_id' => $saleId, 'receipt_number' => $receiptNumber, 'discount_amount' => $discount, 'coupon_code' => $couponCode]);
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
