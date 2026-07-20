<?php

declare(strict_types=1);

$userId = (int) CURRENT_USER_ID;
$storeId = activeStoreId();
$stats = getCashierSalesStats($db, $userId, $storeId);
$todaySales = getCashierTodaySales($db, $userId, $storeId);
$recentSales = getCashierRecentSales($db, $userId, $storeId, 5);
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> My Sales Stats</h1>
</div>

<div class="cashier-stats-grid">
    <div class="cashier-stat-card">
        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-label">Today's Sales</div>
        <div class="stat-value"><?= money($stats['today_sales']) ?></div>
    </div>
    <div class="cashier-stat-card">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-label">Transactions Today</div>
        <div class="stat-value"><?= $stats['today_transactions'] ?></div>
    </div>
    <div class="cashier-stat-card">
        <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
        <div class="stat-label">Average Sale</div>
        <div class="stat-value"><?= money($stats['average_sale']) ?></div>
    </div>
    <div class="cashier-stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-label">Completed Sales</div>
        <div class="stat-value"><?= $stats['completed_sales'] ?></div>
    </div>
    <div class="cashier-stat-card">
        <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
        <div class="stat-label">Held Sales</div>
        <div class="stat-value"><?= $stats['held_sales'] ?></div>
    </div>
    <div class="cashier-stat-card">
        <div class="stat-icon"><i class="fas fa-tags"></i></div>
        <div class="stat-label">Discounts Given Today</div>
        <div class="stat-value"><?= money($stats['today_discounts']) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-clock"></i> Today's Sales</h2>
    </div>
    <?php if (empty($todaySales)): ?>
    <div class="empty-state">
        <i class="fas fa-receipt"></i>
        <p>No sales yet today.</p>
        <a href="index.php?page=sales" class="btn btn-success"><i class="fas fa-cash-register"></i> Start Selling</a>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Time</th>
                    <th>Payment</th>
                    <th>Discount</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todaySales as $s): ?>
                <tr>
                    <td><strong>#<?= e($s['receipt_number']) ?></strong></td>
                    <td class="fs-12"><?= e(date('H:i', strtotime($s['created_at']))) ?></td>
                    <td><?= e(ucfirst($s['payment_method'])) ?></td>
                    <td><?= $s['discount_code'] ? e($s['discount_code']) . ' ' : '' ?><?= (float) $s['discount_amount'] > 0 ? '-' . money((float) $s['discount_amount']) : '&mdash;' ?></td>
                    <td><strong><?= money((float) $s['total']) ?></strong></td>
                    <td><a href="index.php?page=receipt&id=<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-receipt"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
