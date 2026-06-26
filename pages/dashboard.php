<?php

declare(strict_types=1);

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
    $stmt = $db->query("SELECT COUNT(*) FROM stores");
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
} elseif ($percentage >= 40) {
    $statusText = 'Good Progress';
    $barColor = 'var(--warning)';
} elseif ($percentage > 0) {
    $statusText = 'Slow Morning';
    $barColor = 'var(--danger)';
} else {
    $statusText = 'No sales yet today';
    $barColor = 'var(--gray-400)';
}
?>
<div class="page-header">
    <h1>Dashboard</h1>
    <span class="badge badge-info"><?= date('l, F j, Y') ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-info">
            <h3><?= money((float) ($userRole === 'admin' ? ($allTodaySales['total'] ?? 0) : $todaySales['total'])) ?></h3>
            <p><?= $userRole === 'admin' ? 'All Stores Today' : "Today's Sales" ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3><?= (int) ($userRole === 'admin' ? ($allTodaySales['count'] ?? 0) : $todaySales['count']) ?></h3>
            <p><?= $userRole === 'admin' ? 'Transactions All Stores' : 'Transactions Today' ?></p>
        </div>
    </div>
    <?php if ($userRole === 'admin'): ?>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-store"></i></div>
        <div class="stat-info">
            <h3><?= $storeCount ?></h3>
            <p>Active Stores</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-boxes"></i></div>
        <div class="stat-info">
            <h3><?= $allLowStockCount ?></h3>
            <p>Low Stock Items</p>
        </div>
    </div>
    <?php elseif ($userRole !== 'cashier'): ?>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-boxes"></i></div>
        <div class="stat-info">
            <h3><?= count($lowStock) ?></h3>
            <p>Low Stock Items</p>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?= e(ucfirst(userRole() ?? '')) ?></h3>
            <p>Logged in as <?= e(userName() ?? '') ?></p>
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
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <h2><i class="fas fa-store"></i> Stores Overview</h2>
        <a href="index.php?page=stores" class="btn btn-sm btn-primary"><i class="fas fa-cog"></i> Manage Stores</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;padding:4px 0">
        <?php foreach ($allStores as $s): ?>
        <?php $d = $storeData[$s['id']]; $isActive = (int) $s['id'] === ACTIVE_STORE_ID; ?>
        <div style="border:1px solid <?= $isActive ? 'var(--primary)' : 'var(--gray-200)' ?>;border-radius:var(--radius);padding:16px;background:<?= $isActive ? 'var(--bg-active-light)' : 'var(--gray-50)' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div>
                    <strong style="font-size:15px"><?= e($s['name']) ?></strong>
                    <?php if ($isActive): ?>
                        <span class="badge badge-primary" style="margin-left:6px;font-size:11px">Active</span>
                    <?php endif; ?>
                </div>
                <span style="font-size:13px;color:var(--gray-500)"><?= e($s['currency'] ?? 'R') ?> <?= (float) ($s['tax_rate'] ?? 15) ?>%</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px">Today Sales</div>
                    <div style="font-size:18px;font-weight:700;color:var(--success)"><?= money($d['today_sales']) ?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px">Transactions</div>
                    <div style="font-size:18px;font-weight:700"><?= $d['today_transactions'] ?> <span style="font-size:12px;color:var(--gray-400)">/ <?= $d['total_transactions'] ?></span></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px">Products</div>
                    <div style="font-size:18px;font-weight:700"><?= $d['product_count'] ?></div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px">Low Stock</div>
                    <div style="font-size:18px;font-weight:700;color:<?= $d['low_stock'] > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $d['low_stock'] ?></div>
                </div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if (!$isActive): ?>
                <a href="index.php?switch_store=<?= (int) $s['id'] ?>" class="btn btn-sm btn-primary" style="font-size:12px"><i class="fas fa-eye"></i> Switch to Store</a>
                <?php endif; ?>
                <a href="index.php?page=store-form&id=<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline" style="font-size:12px"><i class="fas fa-edit"></i> Edit Store</a>
            </div>
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
$healthPeriods = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
?>

<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-trophy"></i> Store Performance Rankings</h2>
            <form method="get" style="display:flex;gap:6px">
                <input type="hidden" name="page" value="dashboard">
                <select name="perf_period" class="form-control" style="font-size:12px;padding:4px 8px" onchange="this.form.submit()">
                    <?php foreach ($healthPeriods as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $perfPeriod === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if (empty($rankings)): ?>
        <p class="muted">No sales data for this period.</p>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Store</th>
                        <th>Revenue</th>
                        <th>Transactions</th>
                        <th>Avg Transaction</th>
                        <th>Items Sold</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalRevenue = array_sum(array_map(fn($rr) => (float) $rr['revenue'], $rankings)); ?>
                    <?php foreach ($rankings as $i => $r): ?>
                    <?php $share = $totalRevenue > 0 ? round(((float) $r['revenue'] / $totalRevenue) * 100, 1) : 0; ?>
                    <tr style="<?= $i === 0 ? 'background:var(--bg-success-light)' : ($i === count($rankings) - 1 ? 'background:var(--bg-danger-light)' : '') ?>">
                        <td style="font-weight:700;font-size:16px"><?= $i + 1 ?></td>
                        <td><strong><?= e($r['name']) ?></strong>
                            <?php if ($i === 0): ?><span class="badge badge-success" style="font-size:10px">TOP</span><?php endif; ?>
                            <?php if ($i === count($rankings) - 1 && count($rankings) > 1): ?><span class="badge badge-danger" style="font-size:10px">LOW</span><?php endif; ?>
                        </td>
                        <td><strong><?= money((float) $r['revenue']) ?></strong></td>
                        <td><?= (int) $r['transactions'] ?></td>
                        <td><?= money((float) $r['avg_transaction']) ?></td>
                        <td><?= (int) $r['items_sold'] ?></td>
                        <td style="font-weight:700;color:<?= $share >= 50 ? 'var(--success)' : ($share >= 20 ? 'var(--warning)' : 'var(--gray-500)') ?>"><?= $share ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-heartbeat"></i> System Health</h2>
            <a href="?page=dashboard&run_backup=1" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> Run Backup</a>
        </div>
        <?php if ($backupResult): ?>
        <div class="alert alert-<?= $backupResult['success'] ? 'success' : 'danger' ?>" style="margin-bottom:12px"><?= e($backupResult['message']) ?></div>
        <?php endif; ?>
        <div style="display:grid;gap:10px">
            <?php
            $healthItems = [
                'database' => ['Database', 'fa-database'],
                'db_size' => ['Database Size', 'fa-hdd'],
                'server_uptime' => ['Server Uptime', 'fa-clock'],
                'memory' => ['PHP Memory', 'fa-microchip'],
                'storage' => ['Disk Space', 'fa-save'],
                'last_backup' => ['Last Backup', 'fa-archive'],
                'failed_backups' => ['Failed Backups', 'fa-exclamation-triangle'],
            ];
            ?>
            <?php foreach ($healthItems as $key => $item): ?>
            <?php $h = $systemHealth[$key] ?? ['status' => 'unknown', 'message' => 'N/A']; ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:8px;background:<?= $h['status'] === 'healthy' ? 'var(--gray-50)' : ($h['status'] === 'warning' ? 'var(--bg-warning-light)' : 'var(--bg-danger-light)') ?>;border:1px solid <?= $h['status'] === 'healthy' ? 'var(--gray-200)' : ($h['status'] === 'warning' ? 'var(--border-warning)' : 'var(--border-danger)') ?>">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?= $h['status'] === 'healthy' ? 'var(--success)' : ($h['status'] === 'warning' ? 'var(--warning)' : 'var(--danger)') ?>"></span>
                    <i class="fas <?= $item[1] ?>" style="color:var(--gray-500);width:16px;text-align:center"></i>
                    <span style="font-size:13px;font-weight:600"><?= e($item[0]) ?></span>
                </div>
                <span style="font-size:12px;color:var(--gray-600);text-align:right;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($h['message']) ?>"><?= e($h['message']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($backupHistory)): ?>
        <div style="margin-top:12px">
            <h3 style="font-size:14px;margin-bottom:8px"><i class="fas fa-history"></i> Recent Backups</h3>
            <div class="table-container">
                <table style="font-size:12px">
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
                            <td><?= e(date('Y-m-d H:i', strtotime($b['created_at']))) ?></td>
                            <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($b['filename']) ?></td>
                            <td><?= $b['file_size'] > 0 ? round((int) $b['file_size'] / 1024, 1) . ' KB' : '—' ?></td>
                            <td><span class="badge <?= $b['status'] === 'completed' ? 'badge-success' : 'badge-danger' ?>"><?= e($b['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($userRole === 'admin'): ?>
<?php
$allLowStock = getAllLowStockProducts($db, 20);
$allRecentSales = getRecentSalesAllStores($db, 10);
?>
<div class="grid-2" style="margin-bottom:20px">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Across All Stores</h2>
            <a href="index.php?page=inventory" class="btn btn-sm btn-outline"><i class="fas fa-boxes"></i> Inventory</a>
        </div>
        <?php if (empty($allLowStock)): ?>
            <p class="muted">All products are well-stocked across all stores.</p>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Product</th>
                        <th>Stock</th>
                        <th>Threshold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allLowStock as $p): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= e($p['store_name']) ?></span></td>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td>
                            <span class="badge <?= (int) $p['stock_quantity'] === 0 ? 'badge-danger' : 'badge-warning' ?>">
                                <?= (int) $p['stock_quantity'] ?>
                            </span>
                        </td>
                        <td><?= (int) $p['low_stock_threshold'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Recent Sales Across All Stores</h2>
            <a href="index.php?page=sales" class="btn btn-sm btn-outline"><i class="fas fa-shopping-cart"></i> All Sales</a>
        </div>
        <?php if (empty($allRecentSales)): ?>
            <p class="muted">No sales have been made yet.</p>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Store</th>
                        <th>Cashier</th>
                        <th>Total</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRecentSales as $sale): ?>
                    <tr>
                        <td><?= e($sale['receipt_number']) ?></td>
                        <td><span class="badge badge-info"><?= e($sale['store_name']) ?></span></td>
                        <td><?= e($sale['cashier_name']) ?></td>
                        <td><strong><?= money((float) $sale['total']) ?></strong></td>
                        <td><?= e(date('H:i', strtotime($sale['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: /* Non-admin roles - manager, cashier, store_admin */ ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> Sales Progress</h2>
        </div>
        <div style="display:grid;gap:14px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <div style="font-size:13px;color:var(--gray-500)">Target</div>
                    <div style="font-size:20px;font-weight:700"><?= money($dailyTarget) ?></div>
                </div>
                <div>
                    <div style="font-size:13px;color:var(--gray-500)">Current Sales</div>
                    <div style="font-size:20px;font-weight:700;color:var(--primary)"><?= money($todayTotal) ?></div>
                </div>
                <div>
                    <div style="font-size:13px;color:var(--gray-500)">Remaining</div>
                    <div style="font-size:20px;font-weight:700;color:<?= $remaining > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= money($remaining) ?></div>
                </div>
                <div>
                    <div style="font-size:13px;color:var(--gray-500)">Completed</div>
                    <div style="font-size:20px;font-weight:700"><?= $percentage ?>%</div>
                </div>
            </div>
            <div style="background:var(--gray-100);border-radius:50px;height:14px;overflow:hidden;position:relative">
                <div style="height:100%;border-radius:50px;width:<?= $percentage ?>%;background:<?= $barColor ?>;transition:width 1s ease"></div>
            </div>
            <div style="text-align:center;font-weight:600;font-size:15px;color:<?= $barColor ?>">
                <i class="fas fa-<?= $percentage >= 70 ? 'trophy' : ($percentage >= 40 ? 'arrow-up' : ($percentage > 0 ? 'clock' : 'circle')) ?>"></i>
                <?= e($statusText) ?>
            </div>
        </div>
    </div>

    <?php if ($userRole !== 'cashier'): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h2>
            <div style="display:flex;gap:6px">
                <button id="view-graph" class="btn btn-sm btn-primary" onclick="showLowStockView('graph')"><i class="fas fa-chart-bar"></i> Graph</button>
                <button id="view-table" class="btn btn-sm btn-outline" onclick="showLowStockView('table')"><i class="fas fa-table"></i> Table</button>
            </div>
        </div>
        <div id="lowstock-graph" style="max-height:280px">
            <?php if (empty($lowStock)): ?>
                <p class="muted">All products are well-stocked.</p>
            <?php else: ?>
                <canvas id="lowStockChart"></canvas>
            <?php endif; ?>
        </div>
        <div id="lowstock-table" style="display:none">
            <?php if (empty($lowStock)): ?>
                <p class="muted">All products are well-stocked.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStock as $p): ?>
                                <tr>
                                    <td><?= e($p['name']) ?></td>
                                    <td>
                                        <span class="badge <?= $p['stock_quantity'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                                            <?= (int) $p['stock_quantity'] ?>
                                        </span>
                                    </td>
                                    <td><?= (int) $p['low_stock_threshold'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($cashierSales) && in_array($userRole, ['manager'], true)): ?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-users"></i> Cashier Progress Today</h2>
    </div>
    <div style="display:grid;gap:18px">
        <?php foreach ($cashierSales as $cs): ?>
            <?php
                $csTotal = (float) $cs['total'];
                $csPct = $dailyTarget > 0 ? min(100, round(($csTotal / $dailyTarget) * 100, 1)) : 0;
                $csColor = $csPct >= 70 ? 'var(--success)' : ($csPct >= 40 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <div>
                        <strong style="font-size:15px"><i class="fas fa-user"></i> <?= e($cs['cashier_name']) ?></strong>
                        <span style="font-size:13px;color:var(--gray-500);margin-left:10px"><?= (int) $cs['transactions'] ?> transactions</span>
                    </div>
                    <div style="text-align:right">
                        <span style="font-weight:700;font-size:15px"><?= money($csTotal) ?></span>
                        <span style="font-size:13px;color:var(--gray-500);margin-left:8px"><?= $csPct ?>%</span>
                    </div>
                </div>
                <div style="background:var(--gray-100);border-radius:50px;height:10px;overflow:hidden">
                    <div style="height:100%;border-radius:50px;width:<?= $csPct ?>%;background:<?= $csColor ?>;transition:width 1s ease"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> Recent Sales</h2>
    </div>
    <?php if (empty($recentSales)): ?>
        <p class="muted">No sales yet.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Cashier</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                        <tr>
                            <td><?= e($sale['receipt_number']) ?></td>
                            <td><?= e($sale['cashier_name']) ?></td>
                            <td><?= money((float) $sale['total']) ?></td>
                            <td><span class="badge badge-info"><?= e(ucfirst($sale['payment_method'])) ?></span></td>
                            <td><?= e(date('H:i', strtotime($sale['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; /* end admin vs non-admin split */ ?>

<?php if ($userRole !== 'admin' && !empty($lowStock) && $userRole !== 'cashier'): ?>
<script>
function showLowStockView(view) {
    document.getElementById('lowstock-graph').style.display = view === 'graph' ? 'block' : 'none';
    document.getElementById('lowstock-table').style.display = view === 'table' ? 'block' : 'none';
    document.getElementById('view-graph').className = view === 'graph' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
    document.getElementById('view-table').className = view === 'table' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
}
const chartEl = document.getElementById('lowStockChart');
if (chartEl) {
const ctx = chartEl.getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($lowStock as $p): ?>'<?= e($p['name']) ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Current Stock',
            data: [<?php foreach ($lowStock as $p): ?><?= (int) $p['stock_quantity'] ?>,<?php endforeach; ?>],
            backgroundColor: [<?php foreach ($lowStock as $p): ?>'<?= (int) $p['stock_quantity'] === 0 ? '#dc2626' : ((int) $p['stock_quantity'] <= (int) ($p['low_stock_threshold'] / 2) ? '#f97316' : '#eab308') ?>',<?php endforeach; ?>],
            borderColor: '#ffffff', borderWidth: 2, borderRadius: 6,
        }, {
            label: 'Threshold',
            data: [<?php foreach ($lowStock as $p): ?><?= (int) $p['low_stock_threshold'] ?>,<?php endforeach; ?>],
            backgroundColor: 'rgba(37, 99, 235, 0.15)',
            borderColor: '#2563eb', borderWidth: 2, borderRadius: 6, borderDash: [5, 5],
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1f2937', titleFont: { size: 13 }, bodyFont: { size: 13 }, cornerRadius: 8, padding: 10 } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false }, ticks: { font: { size: 11 } } } }
    }
});
}
</script>
<?php endif; ?>
