<?php

declare(strict_types=1);

$user = requireAuth($db);
$apiStoreId = requireApiStore($db, $user);

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

            $paymentMethod = $input['payment_method'] ?? 'cash';
            $cashAmount = (float) ($input['cash_amount'] ?? 0);
            $cardAmount = (float) ($input['card_amount'] ?? 0);
            $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
            $customerName = trim((string) ($input['customer_name'] ?? ''));
            $customerEmail = trim((string) ($input['customer_email'] ?? ''));
            $customerPhone = trim((string) ($input['customer_phone'] ?? ''));

            $db->beginTransaction();
            try {
                $settings = getStoreSettings($db, $apiStoreId);
                $allowedMethods = $settings['allowed_payment_methods'] ?? ['cash', 'card', 'mobile', 'mixed'];
                if (!is_array($allowedMethods) || !$allowedMethods) {
                    $allowedMethods = ['cash', 'card', 'mobile', 'mixed'];
                }
                if (!in_array($paymentMethod, $allowedMethods, true)) {
                    throw new RuntimeException('Payment method is not enabled for this store.');
                }

                // Aggregate quantities before locking stock, so duplicate cart rows
                // cannot bypass the stock check.
                $quantities = [];
                foreach ($cart as $item) {
                    $productId = (int) ($item['id'] ?? $item['product_id'] ?? 0);
                    $qty = (int) ($item['qty'] ?? $item['quantity'] ?? 0);
                    if ($productId <= 0 || $qty <= 0 || $qty > 999) {
                        throw new RuntimeException('Invalid product or quantity in cart.');
                    }
                    $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
                }

                $lockedProduct = $db->prepare("SELECT id, name, price, cost_price, stock_quantity FROM products WHERE id = ? AND store_id = ? AND status = 'active' FOR UPDATE");
                $verifiedItems = [];
                $subtotal = 0.0;
                foreach ($quantities as $productId => $qty) {
                    $lockedProduct->execute([$productId, $apiStoreId]);
                    $product = $lockedProduct->fetch();
                    if (!$product || (int) $product['stock_quantity'] < $qty) {
                        throw new RuntimeException('Insufficient stock for product ID ' . $productId . '.');
                    }
                    $price = (float) $product['price'];
                    $lineTotal = $price * $qty;
                    $subtotal += $lineTotal;
                    $verifiedItems[] = [
                        'id' => (int) $product['id'],
                        'name' => $product['name'],
                        'price' => $price,
                        'cost_price' => (float) $product['cost_price'],
                        'qty' => $qty,
                        'previous_stock' => (int) $product['stock_quantity'],
                        'total' => $lineTotal,
                    ];
                }

                $discountPct = min(100, max(0, (float) ($input['discount_pct'] ?? $input['discount'] ?? 0)));
                $maxDiscount = min(100, max(0, (float) ($settings['max_discount_percentage'] ?? 50)));
                if (($user['role'] ?? '') === 'cashier') {
                    if (empty($settings['cashier_can_apply_discounts']) && $discountPct > 0) {
                        throw new RuntimeException('Cashiers are not permitted to apply manual discounts.');
                    }
                    $maxDiscount = min($maxDiscount, max(0, (float) ($settings['cashier_discount_limit'] ?? 0)));
                }
                if ($discountPct > $maxDiscount) {
                    throw new RuntimeException('The requested discount exceeds your permitted limit.');
                }
                $discountAmount = round($subtotal * ($discountPct / 100), 2);
                $tax = round(($subtotal - $discountAmount) * (TAX_RATE / 100), 2);
                $total = round($subtotal - $discountAmount + $tax, 2);
                if ($paymentMethod === 'cash' && $cashAmount < $total) {
                    throw new RuntimeException('Insufficient cash payment.');
                }
                if ($paymentMethod === 'mixed' && ($cashAmount + $cardAmount) < $total) {
                    throw new RuntimeException('Insufficient mixed payment.');
                }
                if (in_array($paymentMethod, ['card', 'mobile'], true)) {
                    $cardAmount = $total;
                }
                $changeAmount = $paymentMethod === 'cash' ? $cashAmount - $total : ($paymentMethod === 'mixed' ? max(0, $cashAmount - ($total - $cardAmount)) : 0.0);

                if ($customerId) {
                    $customerStmt = $db->prepare("SELECT name, email, phone FROM customers WHERE id = ? AND store_id = ? FOR UPDATE");
                    $customerStmt->execute([$customerId, $apiStoreId]);
                    $customer = $customerStmt->fetch();
                    if (!$customer) {
                        throw new RuntimeException('Customer does not belong to this store.');
                    }
                    $customerName = $customer['name'];
                    $customerEmail = $customer['email'] ?? '';
                    $customerPhone = $customer['phone'] ?? '';
                }

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
                    $apiStoreId,
                ]);

                $saleId = (int) $db->lastInsertId();

                $itemStmt = $db->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, cost_price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ? AND stock_quantity >= ?");

                foreach ($verifiedItems as $item) {
                    $newStock = $item['previous_stock'] - $item['qty'];
                    $itemStmt->execute([$saleId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['cost_price'], $item['total']]);
                    $stockStmt->execute([$item['qty'], $item['id'], $apiStoreId, $item['qty']]);
                    if ($stockStmt->rowCount() !== 1) {
                        throw new RuntimeException('Stock changed while completing the sale. Please retry.');
                    }

                    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?, ?, ?, 'sale', ?, ?, ?, 'Sale', ?)");
                    $adjStmt->execute([$item['id'], (int) $user['id'], $user['full_name'] ?: $user['username'], $item['qty'], $item['previous_stock'], $newStock, $apiStoreId]);
                }

                if ($customerId) {
                    $db->prepare("UPDATE customers SET total_spent = total_spent + ?, visit_count = visit_count + 1 WHERE id = ? AND store_id = ?")
                       ->execute([$total, $customerId, $apiStoreId]);
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
