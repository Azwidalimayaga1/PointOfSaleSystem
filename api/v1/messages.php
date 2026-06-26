<?php

declare(strict_types=1);

$user = requireAuth($db);

switch ($method) {
    case 'GET':
        if ($id === 'unread') {
            if ($user['role'] === 'admin') {
                $messages = getAllUnreadMessages($db);
            } else {
                $count = getUnreadMessageCount($db, (int) $user['id']);
                jsonResponse(['unread_count' => $count]);
                return;
            }
            jsonResponse(['messages' => $messages]);
        }

        if ($user['role'] === 'admin') {
            $messages = getAllMessages($db);
        } else {
            $messages = getUserMessages($db, (int) $user['id']);
        }
        jsonResponse(['messages' => $messages]);

    case 'POST':
        $input = getJsonInput();
        $message = $input['message'] ?? '';

        if (!$message) {
            jsonResponse(['error' => 'Message required'], 400);
        }

        $stmt = $db->prepare("INSERT INTO messages (sender_id, sender_name, message, store_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int) $user['id'], $user['full_name'] ?: $user['username'], $message, ACTIVE_STORE_ID]);

        jsonResponse(['success' => true, 'message_id' => (int) $db->lastInsertId()], 201);

    case 'PUT':
        if ($id && $subResource === 'read') {
            if ($user['role'] === 'admin') {
                $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND store_id = ?");
                $stmt->execute([(int) $id, ACTIVE_STORE_ID]);
            } else {
                $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND sender_id != ? AND store_id = ?");
                $stmt->execute([(int) $id, (int) $user['id'], ACTIVE_STORE_ID]);
            }
            if ($stmt->rowCount() === 0) {
                jsonResponse(['error' => 'Message not found'], 404);
            }
            jsonResponse(['success' => true]);
        }

        jsonResponse(['error' => 'Not found'], 404);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
