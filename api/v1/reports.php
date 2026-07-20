<?php

declare(strict_types=1);

$user = requireAuth($db);
$apiStoreId = requireApiStore($db, $user);
requireRole($user, 'super_admin', 'manager', 'store_admin');

$period = getParam('period', 'today');

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

switch ($id) {
    case 'sales':
        jsonResponse(['report' => salesReport($db, $period)]);
    case 'products':
        jsonResponse(['report' => salesByProduct($db, $period)]);
    case 'cashiers':
        jsonResponse(['report' => salesByCashier($db, $period)]);
    case 'profit':
        jsonResponse(['report' => profitReport($db, $period)]);
    case 'inventory':
        jsonResponse(['report' => inventoryReport($db)]);
    case 'returns':
        jsonResponse(['report' => getReturnsReport($db, $period)]);
    case 'stock':
        jsonResponse(['report' => getStockAdjustmentsReport($db, $period)]);
    case 'logins':
        jsonResponse(['report' => getLoginReport($db, $period)]);
    case 'activity':
        jsonResponse(['report' => getActivityReport($db, $period)]);
    default:
        jsonResponse(['error' => 'Invalid report type'], 400);
}
