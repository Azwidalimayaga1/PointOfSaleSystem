<?php

declare(strict_types=1);

$user = requireAuth($db);

switch ($method) {
    case 'GET':
        if ($id === 'pending' && $user['role'] === 'admin') {
            $stmt = $db->prepare("SELECT rr.*, u.username FROM return_requests rr JOIN users u ON u.id = rr.cashier_id WHERE rr.status = 'pending' AND rr.store_id = ? ORDER BY rr.created_at DESC");
            $stmt->execute([ACTIVE_STORE_ID]);
            jsonResponse(['return_requests' => $stmt->fetchAll()]);
        }

        if ($id) {
            $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? AND store_id = ?");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $returnRequest = $stmt->fetch();
            if (!$returnRequest) {
                jsonResponse(['error' => 'Return request not found'], 404);
            }
            jsonResponse(['return_request' => $returnRequest]);
        }

        $period = getParam('period', '');
        $status = getParam('status', '');

        $sql = "SELECT * FROM return_requests WHERE store_id = ?";
        $params = [ACTIVE_STORE_ID];

        if ($period === 'today') {
            $sql .= " AND DATE(created_at) = CURDATE()";
        } elseif ($period === 'week') {
            $sql .= " AND created_at >= NOW() - INTERVAL 7 DAY";
        } elseif ($period === 'month') {
            $sql .= " AND created_at >= NOW() - INTERVAL 30 DAY";
        }

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        if ($user['role'] !== 'admin') {
            $sql .= " AND cashier_id = ?";
            $params[] = (int) $user['id'];
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['return_requests' => $stmt->fetchAll()]);

    case 'POST':
        $input = getJsonInput();
        $receiptNumber = $input['receipt_number'] ?? '';

        if (!$receiptNumber) {
            jsonResponse(['error' => 'Receipt number required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM sales WHERE receipt_number = ? AND store_id = ?");
        $stmt->execute([$receiptNumber, ACTIVE_STORE_ID]);
        $sale = $stmt->fetch();
        if (!$sale) {
            jsonResponse(['error' => 'Sale not found'], 404);
        }

        $items = $input['items'] ?? [];
        if (empty($items)) {
            jsonResponse(['error' => 'Items required'], 400);
        }

        $reason = $input['reason'] ?? 'return';
        $resolution = $input['resolution'] ?? 'refund';
        $refundAmount = (float) ($input['refund_amount'] ?? 0);
        $exchangeProductId = isset($input['exchange_product_id']) ? (int) $input['exchange_product_id'] : null;
        $exchangeProductName = $input['exchange_product_name'] ?? null;
        $exchangeQty = (int) ($input['exchange_qty'] ?? 0);

        if (!in_array($reason, ['return', 'damage'], true)) {
            jsonResponse(['error' => 'Invalid reason'], 400);
        }
        if (!in_array($resolution, ['refund', 'exchange'], true)) {
            jsonResponse(['error' => 'Invalid resolution'], 400);
        }

        $stmt = $db->prepare("INSERT INTO return_requests (sale_id, receipt_number, cashier_id, cashier_name, items, reason, resolution, refund_amount, exchange_product_id, exchange_product_name, exchange_qty, status, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            (int) $sale['id'],
            $receiptNumber,
            (int) $user['id'],
            $user['full_name'] ?: $user['username'],
            json_encode($items),
            $reason,
            $resolution,
            $refundAmount,
            $exchangeProductId,
            $exchangeProductName,
            $exchangeQty,
            ACTIVE_STORE_ID,
        ]);

        jsonResponse(['success' => true, 'return_id' => (int) $db->lastInsertId()], 201);

    case 'PUT':
        requireRole($user, 'admin');

        if (!$id || $subResource !== 'status') {
            jsonResponse(['error' => 'Not found'], 404);
        }

        $input = getJsonInput();
        $status = $input['status'] ?? '';
        $adminNotes = $input['admin_notes'] ?? '';

        if (!in_array($status, ['approved', 'rejected'], true)) {
            jsonResponse(['error' => 'Status must be approved or rejected'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, ACTIVE_STORE_ID]);
        $returnRequest = $stmt->fetch();

        if (!$returnRequest) {
            jsonResponse(['error' => 'Return request not found'], 404);
        }

        if ($returnRequest['status'] !== 'pending') {
            jsonResponse(['error' => 'Return request already processed'], 400);
        }

        $stmt = $db->prepare("UPDATE return_requests SET status = ?, admin_id = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$status, (int) $user['id'], $adminNotes, (int) $id]);

        if ($status === 'approved') {
            $items = json_decode($returnRequest['items'], true) ?? [];
            $reason = $returnRequest['reason'];
            $resolution = $returnRequest['resolution'];
            $exchangeProductId = $returnRequest['exchange_product_id'] ? (int) $returnRequest['exchange_product_id'] : null;
            $exchangeQty = (int) $returnRequest['exchange_qty'];

            $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($reason === 'return') {
                foreach ($items as $item) {
                    $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
                    $pStmt->execute([(int) $item['product_id'], ACTIVE_STORE_ID]);
                    $prod = $pStmt->fetch();
                    if (!$prod) {
                        throw new RuntimeException("Product ID {$item['product_id']} not found in this store");
                    }
                    $prevStock = (int) ($prod['stock_quantity'] ?? 0);
                    $newStock = $prevStock + (int) $item['qty'];

                    $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND store_id = ?")
                       ->execute([(int) $item['qty'], (int) $item['product_id'], ACTIVE_STORE_ID]);

                    $adjStmt->execute([(int) $item['product_id'], (int) $user['id'], $user['full_name'] ?: $user['username'], 'return', (int) $item['qty'], $prevStock, $newStock, 'Return approved - ' . $reason, ACTIVE_STORE_ID]);
                }

                if ($resolution === 'exchange' && $exchangeProductId) {
                    $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
                    $pStmt->execute([$exchangeProductId, ACTIVE_STORE_ID]);
                    $prod = $pStmt->fetch();
                    if (!$prod) {
                        throw new RuntimeException("Exchange product ID $exchangeProductId not found in this store");
                    }
                    $prevStock = (int) ($prod['stock_quantity'] ?? 0);
                    $newStock = $prevStock - $exchangeQty;

                    $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ?")
                       ->execute([$exchangeQty, $exchangeProductId, ACTIVE_STORE_ID]);

                    $adjStmt->execute([$exchangeProductId, (int) $user['id'], $user['full_name'] ?: $user['username'], 'exchange', $exchangeQty, $prevStock, $newStock, 'Exchange for return', ACTIVE_STORE_ID]);
                }
            } elseif ($reason === 'damage') {
                foreach ($items as $item) {
                    $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
                    $pStmt->execute([(int) $item['product_id'], ACTIVE_STORE_ID]);
                    $prod = $pStmt->fetch();
                    if (!$prod) {
                        throw new RuntimeException("Product ID {$item['product_id']} not found in this store");
                    }
                    $prevStock = (int) ($prod['stock_quantity'] ?? 0);
                    $newStock = $prevStock - (int) $item['qty'];

                    $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ?")
                       ->execute([(int) $item['qty'], (int) $item['product_id'], ACTIVE_STORE_ID]);

                    $adjStmt->execute([(int) $item['product_id'], (int) $user['id'], $user['full_name'] ?: $user['username'], 'damage', (int) $item['qty'], $prevStock, $newStock, 'Damaged item written off', ACTIVE_STORE_ID]);
                }
            }
        }

        logActivity($db, (int) $user['id'], $user['username'], $status === 'approved' ? 'approve_return' : 'reject_return', "Return {$status}: ID $id");

        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
