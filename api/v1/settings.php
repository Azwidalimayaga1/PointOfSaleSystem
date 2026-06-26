<?php

declare(strict_types=1);

$user = requireAuth($db);
requireRole($user, 'admin');

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("SELECT `key`, `value` FROM `settings`");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        jsonResponse(['settings' => $settings]);

    case 'PUT':
        $input = getJsonInput();
        $allowedKeys = ['store_name', 'store_address', 'store_contact', 'tax_rate', 'currency', 'receipt_footer', 'daily_target', 'self_checkout_enabled'];

        if (defined('ACTIVE_STORE_ID') && ACTIVE_STORE_ID > 0) {
            $storeFields = [];
            $storeParams = [];
            foreach ($input as $key => $value) {
                if (in_array($key, $allowedKeys, true)) {
                    $storeFields[] = "$key = ?";
                    $storeParams[] = (string) $value;
                }
            }
            if (!empty($storeFields)) {
                $storeParams[] = ACTIVE_STORE_ID;
                $stmt = $db->prepare("UPDATE stores SET " . implode(', ', $storeFields) . " WHERE id = ?");
                $stmt->execute($storeParams);
            }
        } else {
            $stmt = $db->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            foreach ($input as $key => $value) {
                if (in_array($key, $allowedKeys, true)) {
                    $stmt->execute([$key, (string) $value]);
                }
            }
        }

        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
