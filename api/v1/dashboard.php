<?php

declare(strict_types=1);

$user = requireAuth($db);
$apiStoreId = requireApiStore($db, $user);

if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$today = getTodaySales($db);
$lowStock = getLowStockProducts($db, 10);
$bestSellers = getBestSellers($db, 5);
$recentSales = getRecentSales($db, 5);

$stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE status = 'active' AND store_id = ?");
$stmt->execute([ACTIVE_STORE_ID]);
$totalProducts = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active' AND store_id = ?");
$stmt->execute([ACTIVE_STORE_ID]);
$lowStockCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE store_id = ?");
$stmt->execute([ACTIVE_STORE_ID]);
$totalCustomers = (int) $stmt->fetchColumn();

$expiringProducts = getExpiringProducts($db);
$expiredProducts = getExpiredProducts($db);

$dailyTarget = DAILY_TARGET;
$progress = $dailyTarget > 0 ? min(100, round(($today['total'] / $dailyTarget) * 100)) : 0;

jsonResponse([
    'today_sales' => (float) $today['total'],
    'today_transactions' => (int) $today['count'],
    'total_products' => $totalProducts,
    'low_stock_count' => $lowStockCount,
    'total_customers' => $totalCustomers,
    'daily_target' => $dailyTarget,
    'progress' => $progress,
    'low_stock_products' => $lowStock,
    'best_sellers' => $bestSellers,
    'recent_sales' => $recentSales,
    'expiring_products' => $expiringProducts,
    'expired_products' => $expiredProducts,
]);
