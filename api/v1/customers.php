<?php

declare(strict_types=1);

$user = requireAuth($db);

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND store_id = ?");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $customer = $stmt->fetch();
            if (!$customer) {
                jsonResponse(['error' => 'Customer not found'], 404);
            }

            $stmt = $db->prepare("SELECT * FROM sales WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
            $stmt->execute([$id]);
            $customer['purchase_history'] = $stmt->fetchAll();

            jsonResponse(['customer' => $customer]);
        }

        $search = getParam('search', '');
        $sql = "SELECT * FROM customers WHERE store_id = ?";
        $params = [ACTIVE_STORE_ID];

        if ($search) {
            $sql .= " AND (name LIKE ? OR phone LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['customers' => $stmt->fetchAll()]);

    case 'POST':
        $input = getJsonInput();
        $name = $input['name'] ?? '';
        if (!$name) {
            jsonResponse(['error' => 'Customer name required'], 400);
        }

        $stmt = $db->prepare("INSERT INTO customers (name, phone, email, address, notes, store_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $input['phone'] ?? '',
            $input['email'] ?? '',
            $input['address'] ?? '',
            $input['notes'] ?? '',
            ACTIVE_STORE_ID,
        ]);

        jsonResponse(['success' => true, 'customer_id' => (int) $db->lastInsertId()], 201);

    case 'PUT':
        if (!$id) {
            jsonResponse(['error' => 'Customer ID required'], 400);
        }

        $stmt = $db->prepare("SELECT id FROM customers WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, ACTIVE_STORE_ID]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Customer not found'], 404);
        }

        $input = getJsonInput();
        $fields = [];
        $params = [];

        foreach (['name', 'phone', 'email', 'address', 'notes'] as $f) {
            if (isset($input[$f])) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $params[] = (int) $id;
        $params[] = ACTIVE_STORE_ID;
        $stmt = $db->prepare("UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ? AND store_id = ?");
        $stmt->execute($params);

        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
