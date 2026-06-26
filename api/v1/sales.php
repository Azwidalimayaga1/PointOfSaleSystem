<?php

declare(strict_types=1);

$user = requireAuth($db);

switch ($method) {
    case 'GET':
        if ($id === 'held') {
            $stmt = $db->prepare("SELECT * FROM held_sales WHERE cashier_id = ? AND store_id = ? ORDER BY created_at DESC");
            $stmt->execute([(int) $user['id'], ACTIVE_STORE_ID]);
            jsonResponse(['held_sales' => $stmt->fetchAll()]);
        }

        if ($id) {
            $stmt = $db->prepare("SELECT * FROM sales WHERE id = ? AND store_id = ?");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $sale = $stmt->fetch();
            if (!$sale) {
                jsonResponse(['error' => 'Sale not found'], 404);
            }
            $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$id]);
            $sale['items'] = $stmt->fetchAll();
            jsonResponse(['sale' => $sale]);
        }

        $search = getParam('search', '');
        $period = getParam('period', '');
        $limit = (int) getParam('limit', 50);
        $offset = (int) getParam('offset', 0);

        $sql = "SELECT * FROM sales WHERE store_id = ?";
        $params = [ACTIVE_STORE_ID];

        if ($search) {
            $sql .= " AND (receipt_number LIKE ? OR customer_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($period === 'today') {
            $sql .= " AND DATE(created_at) = CURDATE()";
        } elseif ($period === 'week') {
            $sql .= " AND created_at >= NOW() - INTERVAL 7 DAY";
        } elseif ($period === 'month') {
            $sql .= " AND created_at >= NOW() - INTERVAL 30 DAY";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['sales' => $stmt->fetchAll()]);

    case 'POST':
        if ($id === 'complete') {
            $input = getJsonInput();
            $cart = $input['cart'] ?? [];

            if (empty($cart)) {
                jsonResponse(['error' => 'Cart is empty'], 400);
            }

            $cart = json_decode(json_encode($cart), true);
            if (!is_array($cart) || empty($cart)) {
                jsonResponse(['error' => 'Invalid cart data'], 400);
            }

            $subtotal = (float) ($input['subtotal'] ?? 0);
            $discountPct = (float) ($input['discount'] ?? 0);
            $tax = (float) ($input['tax'] ?? 0);
            $total = (float) ($input['total'] ?? 0);
            $paymentMethod = $input['payment_method'] ?? 'cash';
            $cashAmount = (float) ($input['cash_amount'] ?? 0);
            $cardAmount = (float) ($input['card_amount'] ?? 0);
            $changeAmount = (float) ($input['change_amount'] ?? 0);
            $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
            $customerName = $input['customer_name'] ?? null;
            $customerEmail = $input['customer_email'] ?? null;
            $customerPhone = $input['customer_phone'] ?? null;

            $db->beginTransaction();
            try {
                $receiptNumber = generateReceiptNumber($db);

                $stmt = $db->prepare("INSERT INTO sales (receipt_number, cashier_id, cashier_name, subtotal, tax, tax_rate, discount, discount_type, total, payment_method, cash_amount, card_amount, change_amount, status, customer_id, customer_name, customer_email, customer_phone, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'percentage', ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?)");

                $stmt->execute([
                    $receiptNumber,
                    (int) $user['id'],
                    $user['full_name'] ?: $user['username'],
                    $subtotal,
                    $tax,
                    TAX_RATE,
                    $discountPct,
                    $total,
                    $paymentMethod,
                    $cashAmount,
                    $cardAmount,
                    $changeAmount,
                    $customerId,
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    ACTIVE_STORE_ID,
                ]);

                $saleId = (int) $db->lastInsertId();

                $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ?");

                foreach ($cart as $item) {
                    $productId = (int) ($item['id'] ?? $item['product_id'] ?? 0);
                    $qty = (int) ($item['qty'] ?? $item['quantity'] ?? 1);
                    $itemPrice = (float) ($item['price'] ?? 0);
                    $itemName = $item['name'] ?? $item['product_name'] ?? '';
                    $itemTotal = $itemPrice * $qty;

                    $costStmt = $db->prepare("SELECT cost_price, stock_quantity FROM products WHERE id = ? AND store_id = ?");
                    $costStmt->execute([$productId, ACTIVE_STORE_ID]);
                    $prod = $costStmt->fetch();
                    if (!$prod) {
                        throw new RuntimeException("Product ID $productId not found in this store");
                    }
                    $costPrice = (float) ($prod['cost_price'] ?? 0);
                    $prevStock = (int) ($prod['stock_quantity'] ?? 0);
                    $newStock = $prevStock - $qty;

                    if ($newStock < 0) {
                        throw new RuntimeException("Insufficient stock for product: " . ($itemName ?: "ID $productId"));
                    }

                    $itemStmt->execute([$saleId, $productId, $itemName, $qty, $itemPrice, $costPrice, $itemTotal]);

                    $stockStmt->execute([$qty, $productId, ACTIVE_STORE_ID]);

                    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?, ?, ?, 'sale', ?, ?, ?, 'Sale', ?)");
                    $adjStmt->execute([$productId, (int) $user['id'], $user['full_name'] ?: $user['username'], $qty, $prevStock, $newStock, ACTIVE_STORE_ID]);
                }

                if ($customerId) {
                    $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id = ?")
                       ->execute([$total, $customerId]);
                }

                logActivity($db, (int) $user['id'], $user['username'], 'sale', "Sale completed: $receiptNumber");

                $db->commit();
                jsonResponse(['success' => true, 'sale_id' => $saleId, 'receipt_number' => $receiptNumber], 201);
            } catch (Exception $e) {
                $db->rollBack();
                jsonResponse(['error' => 'Sale failed: ' . $e->getMessage()], 500);
            }
        }

        if ($id === 'hold') {
            $input = getJsonInput();
            $cart = $input['cart'] ?? [];

            if (empty($cart)) {
                jsonResponse(['error' => 'Cart is empty'], 400);
            }

            $cart = json_decode(json_encode($cart), true);

            $stmt = $db->prepare("INSERT INTO held_sales (cashier_id, cashier_name, items, subtotal, discount, tax, total, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                (int) $user['id'],
                $user['full_name'] ?: $user['username'],
                json_encode($cart),
                (float) ($input['subtotal'] ?? 0),
                (int) ($input['discount'] ?? 0),
                (float) ($input['tax'] ?? 0),
                (float) ($input['total'] ?? 0),
                ACTIVE_STORE_ID,
            ]);

            jsonResponse(['success' => true], 201);
        }

        jsonResponse(['error' => 'Not found'], 404);

    case 'DELETE':
        if ($id === 'held' && $subResource) {
            $stmt = $db->prepare("DELETE FROM held_sales WHERE id = ? AND cashier_id = ? AND store_id = ?");
            $stmt->execute([(int) $subResource, (int) $user['id'], ACTIVE_STORE_ID]);
            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Held sale not found'], 404);
            }
            jsonResponse(['success' => true]);
        }

        jsonResponse(['error' => 'Not found'], 404);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
