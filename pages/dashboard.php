<?php

declare(strict_types=1);

$userRole = $_SESSION['user']['role'] ?? userRole();
$todaySales = getTodaySales($db);
$lowStock = getLowStockProducts($db);
$recentSales = getRecentSales($db, 5, $userRole === 'cashier' ? userName() : '');

// Per-cashier sales today
$cashierSales = [];
if (in_array($userRole, ['admin', 'manager'], true)) {
    $stmt = $db->prepare("
        SELECT cashier_name, COUNT(*) as transactions, SUM(total) as total
        FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ?
        GROUP BY cashier_name ORDER BY total DESC
    ");
    $stmt->execute([activeStoreId()]);
    $cashierSales = $stmt->fetchAll();
}

// Admin cross-store totals
$allTodaySales = null;
$allLowStockCount = 0;
$storeCount = 0;
if ($userRole === 'admin') {
    $stmt = $db->query("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()");
    $allTodaySales = $stmt->fetch();
    $stmt = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active'");
    $allLowStockCount = (int) $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM stores WHERE status = 'active'");
    $storeCount = (int) $stmt->fetchColumn();
}

// Sales Progress
$dailyTarget = DAILY_TARGET;
$todayTotal = (float) $todaySales['total'];
$percentage = $dailyTarget > 0 ? min(100, round(($todayTotal / $dailyTarget) * 100, 1)) : 0;
$remaining = max(0, $dailyTarget - $todayTotal);

if ($percentage >= 70) {
    $statusText = 'Excellent Sales Today';
    $barColor = 'var(--success)';
    $barClass = 'success';
} elseif ($percentage >= 40) {
    $statusText = 'Good Progress';
    $barColor = 'var(--warning)';
    $barClass = 'warning';
} elseif ($percentage > 0) {
    $statusText = 'Slow Morning';
    $barColor = 'var(--danger)';
    $barClass = 'danger';
} else {
    $statusText = 'No sales yet today';
    $barColor = 'var(--gray-400)';
    $barClass = '';
}
?>

<!-- Dashboard Header -->
<div class="page-header">
    <h1><i class="fas fa-chart-bar"></i> Dashboard</h1>
    <span class="badge badge-info fs-12"><?= date('l, F j, Y') ?></span>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= money((float) ($userRole === 'admin' ? ($allTodaySales['total'] ?? 0) : $todaySales['total'])) ?></h3>
            <p><?= $userRole === 'admin' ? 'All Stores Today' : "Today's Sales" ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= (int) ($userRole === 'admin' ? ($allTodaySales['count'] ?? 0) : $todaySales['count']) ?></h3>
            <p><?= $userRole === 'admin' ? 'Transactions All Stores' : 'Transactions Today' ?></p>
        </div>
    </div>
    <?php if ($userRole === 'admin'): ?>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-store"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= $storeCount ?></h3>
            <p>Active Stores</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= $allLowStockCount ?></h3>
            <p>Low Stock Items</p>
        </div>
    </div>
    <?php elseif ($userRole !== 'cashier'): ?>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= count($lowStock) ?></h3>
            <p>Low Stock Items</p>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
        <div class="stat-info">
            <h3 class="stat-value" style="font-size:16px"><?= e(ucfirst(userRole() ?? '')) ?></h3>
            <p><?= e(userName() ?? '') ?></p>
        </div>
    </div>
</div>

<?php if ($userRole === 'admin'): ?>
<?php
$allStores = getStores($db);
$storeData = [];
foreach ($allStores as $s) {
    $storeData[$s['id']] = getStoreDashboardData($db, (int) $s['id']);
}
$perfPeriod = $_GET['perf_period'] ?? 'today';
$rankings = getStorePerformanceRankings($db, $perfPeriod);
?>

<!-- Stores Overview -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-store"></i> Stores Overview</h2>
        <a href="index.php?page=stores" class="btn btn-sm btn-outline"><i class="fas fa-cog"></i> Manage Stores</a>
    </div>
    <div class="d-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
        <?php foreach ($allStores as $s): ?>
        <?php $d = $storeData[$s['id']]; $isActive = (int) $s['id'] === ACTIVE_STORE_ID; ?>
        <div class="store-card <?= $isActive ? 'active' : '' ?>">
            <div class="flex-between mb-8">
                <div class="d-flex align-center gap-6">
                    <strong class="fs-14"><?= e($s['name']) ?></strong>
                    <?php if ($isActive): ?>
                        <span class="badge badge-primary fs-10">Active</span>
                    <?php endif; ?>
                </div>
                <span class="fs-12 text-muted"><?= e($s['currency'] ?? 'R') ?> <?= (float) ($s['tax_rate'] ?? 15) ?>%</span>
            </div>
            <div class="store-stat-grid mb-8">
                <div>
                    <div class="text-label">Today Sales</div>
                    <div class="fs-16 fw-bold text-success"><?= money($d['today_sales']) ?></div>
                </div>
                <div>
                    <div class="text-label">Transactions</div>
                    <div class="fs-16 fw-bold"><?= $d['today_transactions'] ?></div>
                </div>
                <div>
                    <div class="text-label">Products</div>
                    <div class="fs-16 fw-bold"><?= $d['product_count'] ?></div>
                </div>
                <div>
                    <div class="text-label">Low Stock</div>
                    <div class="fs-16 fw-bold <?= $d['low_stock'] > 0 ? 'text-danger' : 'text-success' ?>"><?= $d['low_stock'] ?></div>
                </div>
            </div>
            <?php if (!$isActive): ?>
            <form method="post" action="index.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="switch_store" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-primary fs-11"><i class="fas fa-eye"></i> Switch to Store</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Handle backup action
$backupResult = null;
if (isset($_GET['run_backup'])) {
    $backupResult = runBackup($db);
    logAction($db, 'backup_run', 'backup', null, $backupResult['message']);
}
$systemHealth = getSystemHealth($db);
$backupHistory = getBackupHistory($db, 5);

$statusActive = (int) $db->query("SELECT COUNT(*) FROM stores WHERE status = 'active'")->fetchColumn();
$statusPending = (int) $db->query("SELECT COUNT(*) FROM stores WHERE status = 'pending'")->fetchColumn();
$statusInactive = (int) $db->query("SELECT COUNT(*) FROM stores WHERE status = 'inactive'")->fetchColumn();
?>

<div class="grid-2 mb-20">
    <!-- Store Performance Rankings -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-trophy"></i> Store Performance</h2>
            <form method="get" class="d-flex gap-6">
                <input type="hidden" name="page" value="dashboard">
                <select name="perf_period" class="form-control fs-11" style="padding:4px 8px;width:auto" onchange="this.form.submit()" aria-label="Period">
                    <option value="today" <?= $perfPeriod === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $perfPeriod === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $perfPeriod === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="year" <?= $perfPeriod === 'year' ? 'selected' : '' ?>>This Year</option>
                </select>
            </form>
        </div>
        <?php if (empty($rankings)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-line"></i>
            <p>No sales data for this period.</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Store</th>
                        <th>Revenue</th>
                        <th>Sales</th>
                        <th>Avg</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalRevenue = array_sum(array_map(fn($rr) => (float) $rr['revenue'], $rankings)); ?>
                    <?php foreach ($rankings as $i => $r): ?>
                    <?php $share = $totalRevenue > 0 ? round(((float) $r['revenue'] / $totalRevenue) * 100, 1) : 0; ?>
                    <tr>
                        <td class="fw-bold"><?= $i + 1 ?></td>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><strong><?= money((float) $r['revenue']) ?></strong></td>
                        <td><?= (int) $r['transactions'] ?></td>
                        <td><?= money((float) $r['avg_transaction']) ?></td>
                        <td><span class="badge badge-info"><?= $share ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- System Health - Compact -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-heartbeat"></i> System Health</h2>
            <div class="d-flex gap-6">
                <a href="?page=dashboard&run_backup=1" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> Backup</a>
            </div>
        </div>
        <?php if ($backupResult): ?>
        <div class="alert alert-<?= $backupResult['success'] ? 'success' : 'danger' ?> mb-12 fs-12"><?= e($backupResult['message']) ?></div>
        <?php endif; ?>
        <div class="health-widget">
            <?php
            $healthItems = [
                'database' => ['Database', 'fa-database'],
                'storage' => ['Disk Space', 'fa-save'],
                'last_backup' => ['Last Backup', 'fa-archive'],
                'memory' => ['PHP Memory', 'fa-microchip'],
            ];
            ?>
            <?php foreach ($healthItems as $key => $item): ?>
            <?php $h = $systemHealth[$key] ?? ['status' => 'unknown', 'message' => 'N/A']; ?>
            <div class="health-item">
                <span class="status-dot <?= $h['status'] === 'healthy' ? 'healthy' : ($h['status'] === 'warning' ? 'warning' : 'danger') ?>"></span>
                <span class="health-label"><?= e($item[0]) ?></span>
                <span class="health-value"><?= e($h['message']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($backupHistory)): ?>
        <details class="mt-12">
            <summary class="fs-12 text-muted cursor-pointer" style="list-style:none">
                <i class="fas fa-chevron-right fs-10 mr-4"></i> Backup History
            </summary>
            <div class="table-container mt-8">
                <table style="font-size:11px">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backupHistory as $b): ?>
                        <tr>
                            <td class="nowrap"><?= e(date('Y-m-d H:i', strtotime($b['created_at']))) ?></td>
                            <td class="text-truncate" style="max-width:100px"><?= e($b['filename']) ?></td>
                            <td><?= $b['file_size'] > 0 ? round((int) $b['file_size'] / 1024, 1) . ' KB' : '—' ?></td>
                            <td><span class="badge <?= $b['status'] === 'completed' ? 'badge-success' : 'badge-danger' ?>"><?= e($b['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <?php endif; ?>
        <?php if (isset($systemHealth['server_uptime'])): ?>
        <div class="mt-8 fs-11 text-muted text-right">Server Uptime: <?= e($systemHealth['server_uptime']['message']) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Today's Sales by Store Chart + Store Status doughnut -->
<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-bar"></i> Today's Sales by Store</h2>
        </div>
        <?php if (empty($allStores) || array_sum(array_map(fn($s) => (float) ($storeData[$s['id']]['today_sales'] ?? 0), $allStores)) === 0): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <p>No sales yet today. Transactions will appear here once sales are made.</p>
        </div>
        <?php else: ?>
        <div style="height:240px">
            <canvas id="dashSalesByStoreChart" class="w-full h-full"></canvas>
        </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-pie"></i> Store Status</h2>
        </div>
        <?php if ($statusActive === 0 && $statusPending === 0 && $statusInactive === 0): ?>
        <div class="empty-state">
            <i class="fas fa-chart-pie"></i>
            <p>No stores configured yet.</p>
        </div>
        <?php else: ?>
        <div class="d-flex justify-center" style="height:240px">
            <canvas id="dashStatusChart" style="max-width:260px;height:100%"></canvas>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Products by Store Chart -->
<div class="card mb-20">
    <div class="card-header">
        <h2><i class="fas fa-boxes"></i> Products by Store</h2>
    </div>
    <?php if (empty($allStores)): ?>
    <div class="empty-state">
        <i class="fas fa-boxes"></i>
        <p>No store data available yet.</p>
    </div>
    <?php else: ?>
    <div style="height:240px">
        <canvas id="dashProductsByStoreChart" class="w-full h-full"></canvas>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($allStores)): ?>
    var salesCtx = document.getElementById('dashSalesByStoreChart');
    if (salesCtx) {
        new Chart(salesCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($s) => $s['name'], $allStores)) ?>,
                datasets: [{
                    label: "Today's Sales",
                    data: <?= json_encode(array_map(fn($s) => (float) ($storeData[$s['id']]['today_sales'] ?? 0), $allStores)) ?>,
                    backgroundColor: <?= json_encode(array_map(fn($s) => (int) $s['id'] === ACTIVE_STORE_ID ? '#3b82f6' : '#10b981', $allStores)) ?>,
                    borderRadius: 4,
                }, {
                    label: 'Transactions',
                    data: <?= json_encode(array_map(fn($s) => (int) ($storeData[$s['id']]['today_transactions'] ?? 0), $allStores)) ?>,
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    borderColor: '#3b82f6', borderWidth: 2, borderRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 12 } }, tooltip: { backgroundColor: '#1e293b', cornerRadius: 6, padding: 8 } },
                scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } }, x: { grid: { display: false } } }
            }
        });
    }

    var statusCtx = document.getElementById('dashStatusChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Pending', 'Inactive'],
                datasets: [{
                    data: [<?= (int) $statusActive ?>, <?= (int) $statusPending ?>, <?= (int) $statusInactive ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, boxWidth: 8 } },
                    tooltip: { backgroundColor: '#1e293b', cornerRadius: 6, padding: 8 }
                },
                cutout: '70%',
            }
        });
    }

    var prodCtx = document.getElementById('dashProductsByStoreChart');
    if (prodCtx) {
        new Chart(prodCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(fn($s) => $s['name'], $allStores)) ?>,
                datasets: [{
                    label: 'Products',
                    data: <?= json_encode(array_map(fn($s) => (int) ($storeData[$s['id']]['product_count'] ?? 0), $allStores)) ?>,
                    backgroundColor: '#8b5cf6',
                    borderRadius: 4,
                }, {
                    label: 'Low Stock',
                    data: <?= json_encode(array_map(fn($s) => (int) ($storeData[$s['id']]['low_stock'] ?? 0), $allStores)) ?>,
                    backgroundColor: '#ef4444',
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 12 } }, tooltip: { backgroundColor: '#1e293b', cornerRadius: 6, padding: 8 } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.06)' } }, x: { grid: { display: false } } }
            }
        });
    }
    <?php endif; ?>
});
</script>
<?php endif; /* admin */ ?>

<!-- Stock Alerts and Recent Sales (admin) -->
<?php if ($userRole === 'admin'): ?>
<?php
$allLowStock = getAllLowStockProducts($db, 30);
$allRecentSales = getRecentSalesAllStores($db, 8);
?>
<div class="grid-2 mb-20">
    <!-- Stock Alerts -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Stock Alerts</h2>
            <a href="index.php?page=inventory" class="btn btn-sm btn-outline"><i class="fas fa-boxes"></i> Inventory</a>
        </div>
        <?php if (empty($allLowStock)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle text-success"></i>
            <p>All products are well-stocked across all stores.</p>
        </div>
        <?php else:
        // Categorize stock
        $highAlert = array_filter($allLowStock, fn($p) => (int) $p['stock_quantity'] === 0 || $p['status'] !== 'active');
        $lowStockItems = array_filter($allLowStock, fn($p) => (int) $p['stock_quantity'] > 0 && (int) $p['stock_quantity'] <= (int) $p['low_stock_threshold']);
        ?>
        <div class="mb-8 d-flex gap-6 flex-wrap">
            <span class="badge badge-danger">High Alert (<?= count($highAlert) ?>)</span>
            <span class="badge badge-warning">Low Stock (<?= count($lowStockItems) ?>)</span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Store</th>
                        <th>Stock</th>
                        <th>Threshold</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($highAlert as $p): ?>
                    <tr>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><span class="badge badge-primary"><?= e($p['store_name']) ?></span></td>
                        <td><span class="badge badge-danger stock-alert-pulse"><?= (int) $p['stock_quantity'] ?></span></td>
                        <td><?= (int) $p['low_stock_threshold'] ?></td>
                        <td><span class="badge badge-danger"><?= $p['status'] !== 'active' ? 'Unavailable' : 'Out of Stock' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($lowStockItems as $p): ?>
                    <tr>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><span class="badge badge-primary"><?= e($p['store_name']) ?></span></td>
                        <td><span class="badge badge-warning"><?= (int) $p['stock_quantity'] ?></span></td>
                        <td><?= (int) $p['low_stock_threshold'] ?></td>
                        <td><span class="badge badge-warning">Low Stock</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Recent Sales</h2>
            <a href="index.php?page=sales" class="btn btn-sm btn-outline"><i class="fas fa-shopping-cart"></i> View All</a>
        </div>
        <?php if (empty($allRecentSales)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-cart"></i>
            <p>No sales have been made yet.</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Store</th>
                        <th>Cashier</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRecentSales as $sale): ?>
                    <tr>
                        <td class="nowrap"><?= e($sale['receipt_number']) ?></td>
                        <td><span class="badge badge-info"><?= e($sale['store_name']) ?></span></td>
                        <td><?= e($sale['cashier_name']) ?></td>
                        <td><span class="badge badge-gray"><?= e(ucfirst($sale['payment_method'] ?? 'N/A')) ?></span></td>
                        <td><strong><?= money((float) $sale['total']) ?></strong></td>
                        <td class="text-muted"><?= e(date('H:i', strtotime($sale['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: /* Non-admin roles - manager, cashier, store_admin */ ?>

<!-- Sales Progress -->
<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> Sales Progress</h2>
        </div>
        <div class="d-grid gap-12">
            <div class="sales-progress-grid">
                <div>
                    <div class="fs-12 text-muted">Target</div>
                    <div class="fs-18 fw-bold"><?= money($dailyTarget) ?></div>
                </div>
                <div>
                    <div class="fs-12 text-muted">Current Sales</div>
                    <div class="fs-18 fw-bold text-primary"><?= money($todayTotal) ?></div>
                </div>
                <div>
                    <div class="fs-12 text-muted">Remaining</div>
                    <div class="fs-18 fw-bold <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>"><?= money($remaining) ?></div>
                </div>
                <div>
                    <div class="fs-12 text-muted">Completed</div>
                    <div class="fs-18 fw-bold"><?= $percentage ?>%</div>
                </div>
            </div>
            <div class="progress-bar lg">
                <div class="progress-fill <?= $barClass ?>" style="width:<?= $percentage ?>%"></div>
            </div>
            <div class="text-center fw-semibold fs-14" style="color:<?= $barColor ?>">
                <i class="fas fa-<?= $percentage >= 70 ? 'trophy' : ($percentage >= 40 ? 'arrow-up' : ($percentage > 0 ? 'clock' : 'circle')) ?>"></i>
                <?= e($statusText) ?>
            </div>
        </div>
    </div>

    <?php if ($userRole !== 'cashier'): ?>
    <!-- Low Stock Items -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h2>
        </div>
        <?php if (empty($lowStock)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle text-success"></i>
            <p>All products are well-stocked.</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Stock</th>
                        <th>Threshold</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $p):
                        $sQty = (int) $p['stock_quantity'];
                        $threshold = (int) $p['low_stock_threshold'];
                        $pct = $threshold > 0 ? min(100, round(($sQty / $threshold) * 100)) : 0;
                        $isOut = $sQty === 0;
                        $isLow = $sQty > 0 && $sQty < $threshold;
                    ?>
                    <tr>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td>
                            <span class="stock-bar"><span class="stock-bar-fill <?= $isOut ? 'danger' : 'warning' ?>" style="width:<?= $pct ?>%"></span></span>
                            <span class="badge <?= $isOut ? 'badge-danger' : 'badge-warning' ?>"><?= $sQty ?></span>
                        </td>
                        <td><?= $threshold ?></td>
                        <td><span class="badge <?= $isOut ? 'badge-danger' : 'badge-warning' ?>"><?= $isOut ? 'Out of Stock' : 'Low Stock' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($cashierSales) && in_array($userRole, ['manager'], true)): ?>
<!-- Cashier Progress -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-users"></i> Today's Cashier Performance</h2>
    </div>
    <div class="d-grid gap-14">
        <?php foreach ($cashierSales as $cs): ?>
            <?php
                $csTotal = (float) $cs['total'];
                $csPct = $dailyTarget > 0 ? min(100, round(($csTotal / $dailyTarget) * 100, 1)) : 0;
                $csClass = $csPct >= 70 ? 'success' : ($csPct >= 40 ? 'warning' : 'danger');
            ?>
            <div>
                <div class="flex-between mb-4">
                    <div>
                        <strong class="fs-14"><i class="fas fa-user"></i> <?= e($cs['cashier_name']) ?></strong>
                        <span class="fs-12 text-muted ml-8"><?= (int) $cs['transactions'] ?> transactions</span>
                    </div>
                    <div class="text-right">
                        <span class="fw-bold fs-14"><?= money($csTotal) ?></span>
                        <span class="fs-12 text-muted ml-6"><?= $csPct ?>%</span>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?= $csClass ?>" style="width:<?= $csPct ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Sales (non-admin) -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> Recent Sales</h2>
        <a href="index.php?page=sales" class="btn btn-sm btn-outline"><i class="fas fa-shopping-cart"></i> View All</a>
    </div>
    <?php if (empty($recentSales)): ?>
    <div class="empty-state">
        <i class="fas fa-shopping-cart"></i>
        <p>No sales recorded today.</p>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Cashier</th>
                    <th>Payment</th>
                    <th>Total</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td class="nowrap"><?= e($sale['receipt_number']) ?></td>
                        <td><?= e($sale['cashier_name']) ?></td>
                        <td><span class="badge badge-gray"><?= e(ucfirst($sale['payment_method'] ?? 'N/A')) ?></span></td>
                        <td><strong><?= money((float) $sale['total']) ?></strong></td>
                        <td class="text-muted"><?= e(date('H:i', strtotime($sale['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; /* end admin vs non-admin split */ ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-value').forEach(function (el) {
        el.classList.add('animate');
    });
});
</script>
