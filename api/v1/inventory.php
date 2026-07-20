<?php

declare(strict_types=1);

$user = requireAuth($db);
$apiStoreId = requireApiStore($db, $user);
requireRole($user, 'super_admin', 'manager', 'store_admin');

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $product = $stmt->fetch();
            if (!$product) {
                jsonResponse(['error' => 'Product not found'], 404);
            }

            $stmt = $db->prepare("SELECT * FROM stock_adjustments WHERE product_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $product['stock_history'] = $stmt->fetchAll();

            jsonResponse(['product' => $product]);
        }

        $search = getParam('search', '');
        $sql = "SELECT * FROM products WHERE store_id = ?";
        $params = [ACTIVE_STORE_ID];

        if ($search) {
            $sql .= " AND (name LIKE ? OR barcode LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        foreach ($products as &$p) {
            $p['stock_value'] = (float) $p['stock_quantity'] * (float) $p['cost_price'];
        }

        jsonResponse(['products' => $products]);

    case 'POST':
        if ($id === 'adjust') {
            $input = getJsonInput();
            $productId = (int) ($input['product_id'] ?? 0);
            $type = $input['type'] ?? '';
            $quantity = (int) ($input['quantity'] ?? 0);
            $reason = $input['reason'] ?? '';

            if (!$productId || !$type || !$quantity) {
                jsonResponse(['error' => 'Product ID, type, and quantity required'], 400);
            }

            $validTypes = ['sale', 'purchase', 'return', 'adjustment', 'damage'];
            if (!in_array($type, $validTypes, true)) {
                jsonResponse(['error' => 'Invalid adjustment type'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
            $stmt->execute([$productId, ACTIVE_STORE_ID]);
            $product = $stmt->fetch();
            if (!$product) {
                jsonResponse(['error' => 'Product not found'], 404);
            }

            $prevStock = (int) $product['stock_quantity'];

            if ($type === 'sale' || $type === 'damage') {
                $newStock = $prevStock - $quantity;
            } else {
                $newStock = $prevStock + $quantity;
            }

            if ($newStock < 0) {
                jsonResponse(['error' => 'Insufficient stock'], 400);
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE products SET stock_quantity = ? WHERE id = ? AND store_id = ?");
                $stmt->execute([$newStock, $productId, ACTIVE_STORE_ID]);

                $stmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$productId, (int) $user['id'], $user['full_name'] ?: $user['username'], $type, $quantity, $prevStock, $newStock, $reason, ACTIVE_STORE_ID]);

                $db->commit();
                jsonResponse(['success' => true, 'previous_stock' => $prevStock, 'new_stock' => $newStock]);
            } catch (Exception $e) {
                $db->rollBack();
                jsonResponse(['error' => 'Adjustment failed: ' . $e->getMessage()], 500);
            }
        }

        jsonResponse(['error' => 'Not found'], 404);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
