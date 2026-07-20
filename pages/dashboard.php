<?php

declare(strict_types=1);

$userRole = userRole();

// Load dashboard customization from store settings
$dashStoreSettings = getStoreSettings($db, activeStoreId());
$dashWidgets = $dashStoreSettings['dashboard_widgets'] ?? [];
$dashWelcomeMsg = $dashStoreSettings['dashboard_welcome_message'] ?? '';
$dashDisplayName = $dashStoreSettings['store_display_name'] ?? '';

if (empty($dashWidgets)) {
    $dashWidgets = ['today_sales', 'transactions_today', 'low_stock_items', 'best_sellers', 'recent_sales', 'sales_target', 'reminders', 'staff_performance', 'pending_returns', 'active_coupons', 'stock_alerts', 'customer_followups'];
}

$todaySales = getTodaySales($db);
$lowStock = getLowStockProducts($db);
$recentSales = getRecentSales($db, 5, $userRole === 'cashier' ? userName() : '');

// Today's discount stats
$todayDiscountData = getDiscountImpact($db, 'today');

// Per-cashier sales today
$cashierSales = [];
if (in_array($userRole, ['super_admin', 'manager'], true)) {
    if (isSuperAdmin()) {
        $stmt = $db->query("SELECT cashier_name, COUNT(*) as transactions, SUM(total) as total FROM sales WHERE DATE(created_at) = CURDATE() GROUP BY cashier_name ORDER BY total DESC");
    } else {
        $stmt = $db->prepare("SELECT cashier_name, COUNT(*) as transactions, SUM(total) as total FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ? GROUP BY cashier_name ORDER BY total DESC");
        $stmt->execute([activeStoreId()]);
    }
    $cashierSales = $stmt->fetchAll();
}

// Admin cross-store totals
$allTodaySales = null;
$allLowStockCount = 0;
$storeCount = 0;
if (isSuperAdmin()) {
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

// Current store name
$currentStoreName = '';
if (isStoreAdmin() || isManager()) {
    $stmt = $db->prepare("SELECT name FROM stores WHERE id = ?");
    $stmt->execute([activeStoreId()]);
    $cs = $stmt->fetch();
    $currentStoreName = $cs ? $cs['name'] : '';
}

// Get today's reminders for widget
$todayReminders = [];
if (isSuperAdmin()) {
    $stmt = $db->query("SELECT cr.*, u.full_name as assigned_user_name, s.name as store_name FROM calendar_reminders cr LEFT JOIN users u ON u.id = cr.assigned_to_user_id LEFT JOIN stores s ON s.id = cr.store_id WHERE cr.status = 'pending' AND cr.reminder_date <= CURDATE() ORDER BY cr.priority DESC, cr.reminder_date ASC LIMIT 10");
    $todayReminders = $stmt->fetchAll();
} elseif (isStoreAdmin() || isManager()) {
    $stmt = $db->prepare("SELECT cr.*, u.full_name as assigned_user_name, s.name as store_name FROM calendar_reminders cr LEFT JOIN users u ON u.id = cr.assigned_to_user_id LEFT JOIN stores s ON s.id = cr.store_id WHERE cr.status = 'pending' AND cr.reminder_date <= CURDATE() AND cr.store_id = ? ORDER BY cr.priority DESC, cr.reminder_date ASC LIMIT 10");
    $stmt->execute([activeStoreId()]);
    $todayReminders = $stmt->fetchAll();
} elseif (isCashier()) {
    $stmt = $db->prepare("SELECT cr.*, u.full_name as assigned_user_name, s.name as store_name FROM calendar_reminders cr LEFT JOIN users u ON u.id = cr.assigned_to_user_id LEFT JOIN stores s ON s.id = cr.store_id WHERE cr.status = 'pending' AND cr.reminder_date <= CURDATE() AND (cr.assigned_to_user_id = ? OR (cr.is_store_wide = 1 AND cr.store_id = ?)) ORDER BY cr.priority DESC, cr.reminder_date ASC LIMIT 10");
    $stmt->execute([CURRENT_USER_ID, currentUserStoreId()]);
    $todayReminders = $stmt->fetchAll();
}
$pendingReminderCount = count($todayReminders);
$overdueReminders = array_filter($todayReminders, fn($r) => $r['reminder_date'] < date('Y-m-d'));
$dashboardHour = (int) date('G');
$dashboardGreeting = $dashboardHour < 12 ? 'Good morning' : ($dashboardHour < 18 ? 'Good afternoon' : 'Good evening');
?>

<section class="dashboard-hero" aria-labelledby="dashboard-hero-title">
    <div>
        <p class="workspace-eyebrow"><i class="fas fa-chart-line" aria-hidden="true"></i> <?= e(date('l, j F')) ?></p>
        <h1 id="dashboard-hero-title"><?= e($dashboardGreeting) ?>, <?= e(userName() ?: 'there') ?></h1>
        <p><?= e($dashWelcomeMsg ?: 'Here is your store at a glance. Start with the items that need attention today.') ?></p>
    </div>
    <div class="dashboard-hero-actions">
        <a href="index.php?page=sales" class="btn btn-primary"><i class="fas fa-cash-register"></i> New Sale</a>
        <?php if (!isCashier()): ?>
        <a href="index.php?page=inventory" class="btn btn-outline"><i class="fas fa-boxes"></i> Inventory</a>
        <?php endif; ?>
        <a href="index.php?page=calendar" class="btn btn-outline"><i class="fas fa-calendar-alt"></i> Calendar</a>
    </div>
</section>

<?php if (isCashier()): ?>
<!-- ===== CASHIER DASHBOARD ===== -->

<!-- Cashier Profile Card -->
<div class="cashier-profile-card mb-16">
    <div class="cashier-avatar"><?= e(strtoupper(substr(userName() ?? 'C', 0, 1))) ?></div>
    <div>
        <div class="fs-18 fw-bold"><?= e(userName() ?? 'Cashier') ?></div>
        <div class="fs-13 text-muted">Cashier &middot; <?= e($dashDisplayName ?: ($currentStoreName ?: STORE_NAME)) ?></div>
        <?php if ($dashWelcomeMsg): ?>
            <div class="fs-12 text-muted mt-4"><?= e($dashWelcomeMsg) ?></div>
        <?php endif; ?>
    </div>
    <div class="ml-auto d-flex gap-8">
        <a href="index.php?page=sales" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> New Sale</a>
        <a href="index.php?page=calendar" class="btn btn-outline"><i class="fas fa-calendar-alt"></i> My Reminders</a>
    </div>
</div>

<!-- Stats -->
<div class="cashier-dash-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= money($todayTotal) ?></h3>
            <p>My Sales Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= (int) $todaySales['count'] ?></h3>
            <p>My Transactions Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= $percentage ?>%</h3>
            <p>Sales Target Progress</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $pendingReminderCount > 0 ? 'red' : 'green' ?>"><i class="fas fa-bell"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= $pendingReminderCount ?></h3>
            <p>Pending Reminders</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="cashier-quick-actions">
    <a href="index.php?page=sales" class="cashier-quick-action">
        <i class="fas fa-cash-register"></i>
        <span>Start Sale</span>
    </a>
    <a href="index.php?page=sales" class="cashier-quick-action">
        <i class="fas fa-pause-circle"></i>
        <span>Continue Held Sale</span>
    </a>
    <a href="index.php?page=receipt" class="cashier-quick-action">
        <i class="fas fa-clock"></i>
        <span>Recent Sales</span>
    </a>
    <a href="index.php?page=calendar" class="cashier-quick-action">
        <i class="fas fa-calendar-check"></i>
        <span>My Reminders</span>
    </a>
</div>

<!-- Sales Progress & Recent Sales -->
<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> My Sales Progress</h2>
        </div>
        <div class="d-grid gap-12">
            <div class="sales-progress-grid">
                <div>
                    <div class="fs-12 text-muted">Target</div>
                    <div class="fs-18 fw-bold"><?= money($dailyTarget) ?></div>
                </div>
                <div>
                    <div class="fs-12 text-muted">My Sales</div>
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

    <div class="card" id="recent-sales">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> My Recent Sales</h2>
            <a href="index.php?page=sales" class="btn btn-sm btn-outline"><i class="fas fa-shopping-cart"></i> View All</a>
        </div>
        <?php if (empty($recentSales)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-cart"></i>
            <p>No sales recorded yet today.</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                        <tr>
                            <td class="nowrap"><?= e($sale['receipt_number']) ?></td>
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

<!-- Today's Reminders -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-bell"></i> My Reminders Today</h2>
        <a href="index.php?page=calendar" class="btn btn-sm btn-outline"><i class="fas fa-calendar-alt"></i> Open Calendar</a>
    </div>
    <?php if (empty($todayReminders)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle text-success"></i>
        <p>No pending reminders. You're all caught up!</p>
    </div>
    <?php else: ?>
    <div class="d-grid gap-8">
        <?php foreach ($todayReminders as $r):
            $isOverdue = $r['reminder_date'] < date('Y-m-d');
            $priClass = $r['priority'] === 'urgent' ? 'pri-urgent' : ($r['priority'] === 'high' ? 'pri-high' : ($r['priority'] === 'medium' ? 'pri-medium' : 'pri-low'));
        ?>
        <div class="reminder-item <?= $priClass ?> <?= $isOverdue ? 'overdue' : '' ?>">
            <span class="priority-dot <?= $priClass ?>"></span>
            <div>
                <div class="fw-semibold fs-13"><?= e($r['title']) ?></div>
                <div class="fs-11 text-muted">
                    <?php if ($r['reminder_time']): ?><?= e(substr($r['reminder_time'], 0, 5)) ?> &middot; <?php endif; ?>
                    <?= e($r['assigned_user_name'] ? 'Assigned to: ' . $r['assigned_user_name'] : 'Store-wide') ?>
                    <?php if ($isOverdue): ?><span class="badge badge-danger ml-4">Overdue</span><?php endif; ?>
                </div>
            </div>
            <div class="ml-auto">
                <button class="btn btn-sm btn-success" onclick="fetch('index.php?page=calendar&action=ajax',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=complete&id=<?= (int) $r['id'] ?>&_csrf=<?= csrf_token() ?>'}).then(r=>r.json()).then(d=>{if(d.success)location.reload()})"><i class="fas fa-check"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ===== ADMIN / MANAGER DASHBOARD ===== -->

<?php if (isStoreAdmin() && $currentStoreName): ?>
<div class="alert alert-info mb-16" style="display:flex;align-items:center;gap:10px;padding:10px 16px">
    <i class="fas fa-store" style="font-size:18px"></i>
    <strong>Managing: <?= e($dashDisplayName ?: $currentStoreName) ?></strong>
    <?php if ($dashWelcomeMsg): ?>
        <span class="text-muted fs-12 ml-8">— <?= e($dashWelcomeMsg) ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= money((float) $todaySales['total']) ?></h3>
            <p>Today's Sales<?= isSuperAdmin() ? ' (All Stores)' : '' ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= (int) $todaySales['count'] ?></h3>
            <p>Transactions Today<?= isSuperAdmin() ? ' (All Stores)' : '' ?></p>
        </div>
    </div>
    <?php if (isSuperAdmin()): ?>
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
            <p>Low Stock Items (All)</p>
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
    <?php if (in_array($userRole, ['super_admin', 'store_admin'], true)): ?>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-tags"></i></div>
        <div class="stat-info">
            <h3 class="stat-value"><?= money((float) ($todayDiscountData['total_discount_given'] ?? 0)) ?></h3>
            <p>Discounts Given Today</p>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
        <div class="stat-info">
            <h3 class="stat-value" style="font-size:16px"><?= e($roleDisplay ?? ucfirst(userRole() ?? '')) ?></h3>
            <p><?= e(userName() ?? '') ?></p>
        </div>
    </div>
</div>

<!-- Today's Reminders Widget -->
<?php if (!empty($todayReminders)): ?>
<div class="card mb-20">
    <div class="card-header">
        <h2><i class="fas fa-bell"></i> Today's Reminders <?= count($overdueReminders) > 0 ? '<span class="badge badge-danger">' . count($overdueReminders) . ' Overdue</span>' : '' ?></h2>
        <a href="index.php?page=calendar" class="btn btn-sm btn-outline"><i class="fas fa-calendar-alt"></i> Calendar</a>
    </div>
    <div class="d-grid gap-8">
        <?php foreach ($todayReminders as $r):
            $isOverdue = $r['reminder_date'] < date('Y-m-d');
            $priClass = $r['priority'] === 'urgent' ? 'pri-urgent' : ($r['priority'] === 'high' ? 'pri-high' : ($r['priority'] === 'medium' ? 'pri-medium' : 'pri-low'));
        ?>
        <div class="reminder-item <?= $priClass ?> <?= $isOverdue ? 'overdue' : '' ?>" style="cursor:default">
            <span class="priority-dot <?= $priClass ?>"></span>
            <div class="flex-1">
                <div class="fw-semibold fs-13"><?= e($r['title']) ?></div>
                <div class="fs-11 text-muted">
                    <?= e($r['store_name'] ?? '') ?>
                    <?php if ($r['reminder_time']): ?> &middot; <?= e(substr($r['reminder_time'], 0, 5)) ?><?php endif; ?>
                    <?php if ($r['assigned_user_name']): ?> &middot; <?= e($r['assigned_user_name']) ?><?php endif; ?>
                    <?php if ($isOverdue): ?><span class="badge badge-danger ml-4">Overdue</span><?php endif; ?>
                </div>
            </div>
            <button class="btn btn-sm btn-success" onclick="fetch('index.php?page=calendar&action=ajax',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=complete&id=<?= (int) $r['id'] ?>&_csrf=<?= csrf_token() ?>'}).then(r=>r.json()).then(d=>{if(d.success)location.reload()})"><i class="fas fa-check"></i> Done</button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (isSuperAdmin()): ?>
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
$backupResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_backup']) && isSuperAdmin() && validate_csrf($_POST['_csrf'] ?? '')) {
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

    <!-- System Health -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-heartbeat"></i> System Health</h2>
            <div class="d-flex gap-6">
                <?php if (isSuperAdmin()): ?>
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" name="run_backup" value="1" class="btn btn-sm btn-primary"><i class="fas fa-database"></i> Backup</button>
                </form>
                <?php endif; ?>
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
                    <thead><tr><th>Date</th><th>File</th><th>Size</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($backupHistory as $b): ?>
                        <tr>
                            <td class="nowrap"><?= e(date('Y-m-d H:i', strtotime($b['created_at']))) ?></td>
                            <td class="text-truncate" style="max-width:100px"><?= e($b['filename']) ?></td>
                            <td><?= $b['file_size'] > 0 ? round((int) $b['file_size'] / 1024, 1) . ' KB' : '&mdash;' ?></td>
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

<!-- Charts -->
<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-bar"></i> Today's Sales by Store</h2>
        </div>
        <?php if (empty($allStores) || array_sum(array_map(fn($s) => (float) ($storeData[$s['id']]['today_sales'] ?? 0), $allStores)) === 0): ?>
        <div class="empty-state"><i class="fas fa-chart-bar"></i><p>No sales yet today.</p></div>
        <?php else: ?>
        <div style="height:240px"><canvas id="dashSalesByStoreChart" class="w-full h-full"></canvas></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-pie"></i> Store Status</h2>
        </div>
        <?php if ($statusActive === 0 && $statusPending === 0 && $statusInactive === 0): ?>
        <div class="empty-state"><i class="fas fa-chart-pie"></i><p>No stores configured yet.</p></div>
        <?php else: ?>
        <div class="d-flex justify-center" style="height:240px"><canvas id="dashStatusChart" style="max-width:260px;height:100%"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-20">
    <div class="card-header">
        <h2><i class="fas fa-boxes"></i> Products by Store</h2>
    </div>
    <?php if (empty($allStores)): ?>
    <div class="empty-state"><i class="fas fa-boxes"></i><p>No store data available yet.</p></div>
    <?php else: ?>
    <div style="height:240px"><canvas id="dashProductsByStoreChart" class="w-full h-full"></canvas></div>
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
                plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, boxWidth: 8 } }, tooltip: { backgroundColor: '#1e293b', cornerRadius: 6, padding: 8 } },
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
<?php endif; /* super_admin */ ?>

<!-- Stock Alerts and Recent Sales (super_admin) -->
<?php if (isSuperAdmin()): ?>
<?php
$allLowStock = getAllLowStockProducts($db, 30);
$allRecentSales = getRecentSalesAllStores($db, 8);
?>
<div class="grid-2 mb-20">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Stock Alerts</h2>
            <a href="index.php?page=inventory" class="btn btn-sm btn-outline"><i class="fas fa-boxes"></i> Inventory</a>
        </div>
        <?php if (empty($allLowStock)): ?>
        <div class="empty-state"><i class="fas fa-check-circle text-success"></i><p>All products are well-stocked across all stores.</p></div>
        <?php else:
        $highAlert = array_filter($allLowStock, fn($p) => (int) $p['stock_quantity'] === 0 || $p['status'] !== 'active');
        $lowStockItems = array_filter($allLowStock, fn($p) => (int) $p['stock_quantity'] > 0 && (int) $p['stock_quantity'] <= (int) $p['low_stock_threshold']);
        ?>
        <div class="mb-8 d-flex gap-6 flex-wrap">
            <span class="badge badge-danger">High Alert (<?= count($highAlert) ?>)</span>
            <span class="badge badge-warning">Low Stock (<?= count($lowStockItems) ?>)</span>
        </div>
        <div class="table-container">
            <table>
                <thead><tr><th>Product</th><th>Store</th><th>Stock</th><th>Threshold</th><th>Status</th></tr></thead>
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

    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Recent Sales</h2>
            <a href="index.php?page=sales" class="btn btn-sm btn-outline"><i class="fas fa-shopping-cart"></i> View All</a>
        </div>
        <?php if (empty($allRecentSales)): ?>
        <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>No sales have been made yet.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Receipt</th><th>Store</th><th>Cashier</th><th>Payment</th><th>Total</th><th>Time</th></tr></thead>
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

<?php else: /* store_admin / manager dashboard */ ?>

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

    <!-- Low Stock Items -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Items</h2>
        </div>
        <?php if (empty($lowStock)): ?>
        <div class="empty-state"><i class="fas fa-check-circle text-success"></i><p>All products are well-stocked.</p></div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Product</th><th>Stock</th><th>Threshold</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($lowStock as $p):
                        $sQty = (int) $p['stock_quantity'];
                        $threshold = (int) $p['low_stock_threshold'];
                        $pct = $threshold > 0 ? min(100, round(($sQty / $threshold) * 100)) : 0;
                        $isOut = $sQty === 0;
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
</div>

<?php if (!empty($cashierSales) && in_array($userRole, ['super_admin', 'manager'], true)): ?>
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

<!-- Recent Sales -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> Recent Sales</h2>
        <a href="index.php?page=sales" class="btn btn-sm btn-outline"><i class="fas fa-shopping-cart"></i> View All</a>
    </div>
    <?php if (empty($recentSales)): ?>
    <div class="empty-state"><i class="fas fa-shopping-cart"></i><p>No sales recorded today.</p></div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead><tr><th>Receipt #</th><th>Cashier</th><th>Payment</th><th>Total</th><th>Time</th></tr></thead>
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

<?php endif; /* end super_admin vs store_admin split */ ?>

<?php endif; /* end cashier vs admin split */ ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-value').forEach(function (el) {
        el.classList.add('animate');
    });
});
</script>
