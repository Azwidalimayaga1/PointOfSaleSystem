<?php

declare(strict_types=1);

$todaySales = getTodaySales($db);
$lowStock = getLowStockProducts($db);
$recentSales = getRecentSales($db, 5, $userRole === 'cashier' ? userName() : '');

// Per-cashier sales today
$cashierSales = [];
if (in_array($userRole, ['admin', 'manager'], true)) {
    $stmt = $db->query("
        SELECT cashier_name, COUNT(*) as transactions, SUM(total) as total
        FROM sales WHERE DATE(created_at) = CURDATE()
        GROUP BY cashier_name ORDER BY total DESC
    ");
    $cashierSales = $stmt->fetchAll();
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
            <h3><?= money((float) $todaySales['total']) ?></h3>
            <p>Today's Sales</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3><?= (int) $todaySales['count'] ?></h3>
            <p>Transactions Today</p>
        </div>
    </div>
    <?php if ($userRole !== 'cashier'): ?>
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

<?php if (!empty($cashierSales) && in_array($userRole, ['admin', 'manager'], true)): ?>
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

<?php if (!empty($lowStock) && $userRole !== 'cashier'): ?>
<script>
function showLowStockView(view) {
    document.getElementById('lowstock-graph').style.display = view === 'graph' ? 'block' : 'none';
    document.getElementById('lowstock-table').style.display = view === 'table' ? 'block' : 'none';
    document.getElementById('view-graph').className = view === 'graph' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
    document.getElementById('view-table').className = view === 'table' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
}

const ctx = document.getElementById('lowStockChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($lowStock as $p): ?>'<?= e($p['name']) ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Current Stock',
            data: [<?php foreach ($lowStock as $p): ?><?= (int) $p['stock_quantity'] ?>,<?php endforeach; ?>],
            backgroundColor: [
                <?php foreach ($lowStock as $p): ?>
                    '<?= ((int) $p['stock_quantity'] === 0) ? '#dc2626' : (((int) $p['stock_quantity'] <= (int) ($p['low_stock_threshold'] / 2)) ? '#f97316' : '#eab308') ?>',
                <?php endforeach; ?>
            ],
            borderColor: '#ffffff',
            borderWidth: 2,
            borderRadius: 6,
        }, {
            label: 'Threshold',
            data: [<?php foreach ($lowStock as $p): ?><?= (int) $p['low_stock_threshold'] ?>,<?php endforeach; ?>],
            backgroundColor: 'rgba(37, 99, 235, 0.15)',
            borderColor: '#2563eb',
            borderWidth: 2,
            borderRadius: 6,
            borderDash: [5, 5],
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1f2937',
                titleFont: { size: 13 },
                bodyFont: { size: 13 },
                cornerRadius: 8,
                padding: 10,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 },
                grid: { color: 'rgba(0,0,0,0.05)' },
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            }
        }
    }
});
</script>
<?php endif; ?>
</div>

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
