<?php

declare(strict_types=1);

header('Content-Type: application/json');

try {
    $data = json_decode($_POST['cart'] ?? '[]', true);
    if (empty($data)) {
        throw new \Exception('Cart is empty.');
    }

    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $cashAmount = (float) ($_POST['cash_amount'] ?? 0);
    $cardAmount = (float) ($_POST['card_amount'] ?? 0);
    $changeAmount = (float) ($_POST['change_amount'] ?? 0);
    $subtotal = (float) ($_POST['subtotal'] ?? 0);
    $discount = (float) ($_POST['discount'] ?? 0);
    $discountPct = (float) ($_POST['discount_pct'] ?? 0);
    $tax = (float) ($_POST['tax'] ?? 0);
    $total = (float) ($_POST['total'] ?? 0);
    $user = $_SESSION['user'];

    $db->beginTransaction();

    $receiptNumber = generateReceiptNumber($db);
    $stmt = $db->prepare("INSERT INTO sales (receipt_number, cashier_id, cashier_name, subtotal, tax, tax_rate, discount, discount_type, total, payment_method, cash_amount, card_amount, change_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $cashierName = $user['display_name'] ?? $user['full_name'];
    $stmt->execute([$receiptNumber, $user['id'], $cashierName, $subtotal, $tax, TAX_RATE, $discount, 'percentage', $total, $paymentMethod, $cashAmount, $cardAmount, $changeAmount]);
    $saleId = (int) $db->lastInsertId();

    $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, total) VALUES (?,?,?,?,?,?,?)");
    $updateStock = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason) VALUES (?,?,?,?,?,?,?,?)");

    foreach ($data as $item) {
        $itemStmt->execute([$saleId, (int) $item['id'], $item['name'], (int) $item['qty'], (float) $item['price'], 0, (float) $item['price'] * (int) $item['qty']]);

        $pStmt = $db->prepare("SELECT stock_quantity, cost_price FROM products WHERE id = ? AND store_id = ?");
        $pStmt->execute([(int) $item['id'], activeStoreId()]);
        $prod = $pStmt->fetch();
        $prevStock = (int) ($prod['stock_quantity'] ?? 0);
        $newStock = $prevStock - (int) $item['qty'];
        $costPrice = (float) ($prod['cost_price'] ?? 0);

        $updateStock->execute([(int) $item['qty'], (int) $item['id']]);
        $adjStmt->execute([(int) $item['id'], $user['id'], $user['full_name'], 'sale', (int) $item['qty'], $prevStock, $newStock, 'Sale ' . $receiptNumber]);

        $db->prepare("UPDATE sale_items SET cost_price = ? WHERE sale_id = ? AND product_id = ?")->execute([$costPrice, $saleId, (int) $item['id']]);
    }

    $db->commit();

    $_SESSION['pos_flash'] = [
        'type' => 'success',
        'message' => "Sale completed! Receipt: {$receiptNumber}",
        'receipt_id' => $saleId,
        'receipt_number' => $receiptNumber,
    ];

    echo json_encode(['success' => true, 'sale_id' => $saleId, 'receipt_number' => $receiptNumber]);
} catch (\Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
