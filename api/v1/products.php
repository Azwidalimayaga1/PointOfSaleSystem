<?php

declare(strict_types=1);

$user = requireAuth($db);
$apiStoreId = requireApiStore($db, $user);

switch ($method) {
    case 'GET':
        if ($id && $subResource === 'barcode') {
            $barcode = $id;
            $stmt = $db->prepare("SELECT id, name, barcode, price, stock_quantity, category, cost_price, image, status FROM products WHERE barcode = ? AND status = 'active' AND store_id = ?");
            $stmt->execute([$barcode, ACTIVE_STORE_ID]);
            $product = $stmt->fetch();
            if (!$product) {
                jsonResponse(['error' => 'Product not found'], 404);
            }
            jsonResponse(['product' => $product]);
        }

        if ($id) {
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $product = $stmt->fetch();
            if (!$product) {
                jsonResponse(['error' => 'Product not found'], 404);
            }
            jsonResponse(['product' => $product]);
        }

        $search = getParam('search', '');
        $category = getParam('category', '');
        $stockFilter = getParam('stock', '');
        $sql = "SELECT * FROM products WHERE store_id = ?";
        $params = [ACTIVE_STORE_ID];

        if ($search) {
            $sql .= " AND (name LIKE ? OR barcode LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($stockFilter === 'low') {
            $sql .= " AND stock_quantity <= low_stock_threshold";
        } elseif ($stockFilter === 'out') {
            $sql .= " AND stock_quantity = 0";
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND store_id = ? ORDER BY category");
        $stmt->execute([ACTIVE_STORE_ID]);
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        jsonResponse(['products' => $products, 'categories' => $categories]);

    case 'POST':
        requireRole($user, 'super_admin', 'manager', 'store_admin');

        $input = getJsonInput();
        $name = $input['name'] ?? '';
        if (!$name) {
            jsonResponse(['error' => 'Product name required'], 400);
        }

        $stmt = $db->prepare("INSERT INTO products (name, barcode, category, price, cost_price, stock_quantity, low_stock_threshold, expiry_date, supplier, status, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $input['barcode'] ?? '',
            $input['category'] ?? '',
            (float) ($input['price'] ?? 0),
            (float) ($input['cost_price'] ?? 0),
            (int) ($input['stock_quantity'] ?? 0),
            (int) ($input['low_stock_threshold'] ?? 10),
            $input['expiry_date'] ?? null,
            $input['supplier'] ?? '',
            $input['status'] ?? 'active',
            ACTIVE_STORE_ID,
        ]);

        $productId = (int) $db->lastInsertId();
        logActivity($db, (int) $user['id'], $user['username'], 'add_product', "Added product: $name");
        jsonResponse(['success' => true, 'product_id' => $productId], 201);

    case 'PUT':
        requireRole($user, 'super_admin', 'manager', 'store_admin');

        if (!$id) {
            jsonResponse(['error' => 'Product ID required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, ACTIVE_STORE_ID]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Product not found'], 404);
        }

        $input = getJsonInput();
        $fields = [];
        $params = [];
        foreach (['name', 'barcode', 'category', 'supplier', 'status', 'image', 'expiry_date'] as $f) {
            if (isset($input[$f])) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }
        foreach (['price', 'cost_price'] as $f) {
            if (isset($input[$f])) {
                $fields[] = "$f = ?";
                $params[] = (float) $input[$f];
            }
        }
        foreach (['stock_quantity', 'low_stock_threshold'] as $f) {
            if (isset($input[$f])) {
                $fields[] = "$f = ?";
                $params[] = (int) $input[$f];
            }
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $params[] = (int) $id;
        $params[] = ACTIVE_STORE_ID;
        $stmt = $db->prepare("UPDATE products SET " . implode(', ', $fields) . " WHERE id = ? AND store_id = ?");
        $stmt->execute($params);

        logActivity($db, (int) $user['id'], $user['username'], 'edit_product', "Edited product ID: $id");
        jsonResponse(['success' => true]);

    case 'DELETE':
        requireRole($user, 'super_admin', 'manager', 'store_admin');

        if (!$id) {
            jsonResponse(['error' => 'Product ID required'], 400);
        }

        $stmt = $db->prepare("SELECT id, name FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, ACTIVE_STORE_ID]);
        $product = $stmt->fetch();
        if (!$product) {
            jsonResponse(['error' => 'Product not found'], 404);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(['error' => 'Cannot delete product with existing sales'], 409);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM stock_adjustments WHERE product_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(['error' => 'Cannot delete product with stock adjustments'], 409);
        }

        $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, ACTIVE_STORE_ID]);

        logActivity($db, (int) $user['id'], $user['username'], 'delete_product', "Deleted product: {$product['name']}");
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
