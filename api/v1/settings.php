<?php

declare(strict_types=1);

$user = requireAuth($db);
requireRole($user, 'super_admin', 'store_admin');

$targetStoreId = $apiStoreId;

switch ($method) {
    case 'GET':
        $store = getStore($db, $targetStoreId);
        $settings = getStoreSettings($db, $targetStoreId);
        $settings['store_name'] = $store['name'] ?? '';
        $settings['store_address'] = $store['address'] ?? '';
        $settings['store_contact'] = $store['contact'] ?? '';
        $settings['tax_rate'] = (string) ($store['tax_rate'] ?? '0');
        $settings['currency'] = $store['currency'] ?? 'ZAR';
        $settings['daily_target'] = (string) ($store['daily_target'] ?? '0');
        $settings['receipt_footer'] = $settings['receipt_footer'] ?: ($store['receipt_footer'] ?? '');
        jsonResponse(['settings' => $settings]);

    case 'PUT':
        $input = getJsonInput();
        unset($input['store_id']); // prevent store_id override

        if (empty($input)) {
            jsonResponse(['error' => 'No settings provided'], 400);
        }

        saveStoreSettings($db, $targetStoreId, $input);
        jsonResponse(['success' => true, 'message' => 'Settings saved.']);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
