<?php

declare(strict_types=1);

$user = requireAuth($db);
requireRole($user, 'admin', 'store_admin');
$isSystemAdmin = $user['role'] === 'admin';

switch ($method) {
    case 'GET':
        if ($id) {
            $storeClause = $isSystemAdmin ? '(store_id = ? OR store_id IS NULL)' : 'store_id = ?';
            $stmt = $db->prepare("SELECT id, username, email, full_name, role, status, created_at FROM users WHERE id = ? AND $storeClause");
            $stmt->execute([$id, ACTIVE_STORE_ID]);
            $u = $stmt->fetch();
            if (!$u) {
                jsonResponse(['error' => 'User not found'], 404);
            }
            jsonResponse(['user' => $u]);
        }

        $storeClause = $isSystemAdmin ? '(store_id = ? OR store_id IS NULL)' : 'store_id = ?';
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, status, created_at FROM users WHERE $storeClause ORDER BY created_at DESC");
        $stmt->execute([ACTIVE_STORE_ID]);
        jsonResponse(['users' => $stmt->fetchAll()]);

    case 'POST':
        $input = getJsonInput();
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $fullName = $input['full_name'] ?? '';
        $role = $input['role'] ?? 'cashier';
        $status = $input['status'] ?? 'active';

        if (!$username || !$fullName) {
            jsonResponse(['error' => 'Username and full name required'], 400);
        }

        if (!in_array($role, ['admin', 'manager', 'cashier', 'store_admin'], true)) {
            jsonResponse(['error' => 'Invalid role'], 400);
        }

        // Store admin cannot create system admin users
        if (!$isSystemAdmin && $role === 'admin') {
            jsonResponse(['error' => 'Forbidden: cannot assign System Admin role'], 403);
        }

        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Username or email already exists'], 409);
        }

        $supabaseId = '';
        if ($supabase && $email && $password) {
            try {
                $result = $supabase->adminCreateUser($email, $password, ['full_name' => $fullName]);
                $supabaseId = $result['user']['id'] ?? '';
            } catch (Exception $e) {
            }
        }

        $hash = $password ? securePasswordHash($password) : '';
        $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role, status, supabase_id, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hash, $fullName, $role, $status, $supabaseId, ACTIVE_STORE_ID]);

        jsonResponse(['success' => true, 'user_id' => (int) $db->lastInsertId()], 201);

    case 'PUT':
        if (!$id) {
            jsonResponse(['error' => 'User ID required'], 400);
        }

        $storeClause = $isSystemAdmin ? '(store_id = ? OR store_id IS NULL)' : 'store_id = ?';
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND $storeClause");
        $stmt->execute([$id, ACTIVE_STORE_ID]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'User not found'], 404);
        }

        $input = getJsonInput();
        $fields = [];
        $params = [];

        foreach (['username', 'email', 'full_name', 'status'] as $f) {
            if (isset($input[$f])) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }

        if (isset($input['role'])) {
            if (!in_array($input['role'], ['admin', 'manager', 'cashier', 'store_admin'], true)) {
                jsonResponse(['error' => 'Invalid role'], 400);
            }
            if (!$isSystemAdmin && $input['role'] === 'admin') {
                jsonResponse(['error' => 'Forbidden: cannot assign System Admin role'], 403);
            }
            $fields[] = "role = ?";
            $params[] = $input['role'];
        }

        if (isset($input['password']) && $input['password']) {
            $fields[] = "password = ?";
            $params[] = securePasswordHash($input['password']);
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $params[] = (int) $id;
        $params[] = ACTIVE_STORE_ID;
        $stmt = $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ? AND $storeClause");
        $stmt->execute($params);

        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
