<?php

declare(strict_types=1);

$period = $_GET['period'] ?? 'today';
$tab = $_GET['tab'] ?? 'sales';

// Handle export
if (isset($_GET['export'])) {
    $format = $_GET['export'];

    if ($tab === 'sales') {
        $data = getSalesForExport($db, $period);
    } elseif ($tab === 'products') {
        $data = getSalesItemsForExport($db, $period);
    } elseif ($tab === 'profit') {
        $data = profitReport($db, $period);
    } else {
        $data = [];
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-' . $tab . '-' . $period . '.csv"');

        $output = fopen('php://output', 'w');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    if ($format === 'pdf') {
        // Simple HTML-to-print PDF
        ?>
        <html><head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 6px 10px; border: 1px solid #ccc; text-align: left; }
            th { background: #f3f4f6; }
            h1 { font-size: 18px; }
            @media print { body { margin: 20px; } }
        </style></head><body>
        <h1><?= e(STORE_NAME) ?> - <?= e(ucfirst($tab)) ?> Report (<?= e(ucfirst($period)) ?>)</h1>
        <table><thead><tr>
        <?php if (!empty($data)): ?>
            <?php foreach (array_keys($data[0]) as $col): ?>
                <th><?= e(ucwords(str_replace('_', ' ', $col))) ?></th>
            <?php endforeach; ?>
        <?php endif; ?>
        </tr></thead><tbody>
        <?php foreach ($data as $row): ?>
            <tr><?php foreach ($row as $val): ?>
                <td><?= e((string) $val) ?></td>
            <?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody></table>
        <p style="margin-top:20px;font-size:11px;color:#666">Generated on <?= date('Y-m-d H:i') ?></p>
        <script>window.print();</script>
        </body></html>
        <?php
        exit;
    }
}

$salesData = salesReport($db, $period);
$productData = salesByProduct($db, $period);
$cashierData = salesByCashier($db, $period);
$profitData = profitReport($db, $period);
$inventoryData = inventoryReport($db);
$salesItems = getSalesItemsForExport($db, $period);

// Summaries
$totalSales = array_sum(array_column($salesData, 'total'));
$totalTransactions = array_sum(array_column($salesData, 'transactions'));
$totalProfit = array_sum(array_column($profitData, 'profit'));
$totalRevenue = array_sum(array_column($profitData, 'revenue'));
$totalCost = array_sum(array_column($profitData, 'cost'));
?>
<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Reports</h1>
    <div class="d-flex gap-8 align-center">
        <a href="?page=reports&period=<?= e($period) ?>&tab=<?= e($tab) ?>&export=csv" class="btn btn-sm btn-secondary"><i class="fas fa-file-csv"></i> CSV</a>
        <a href="?page=reports&period=<?= e($period) ?>&tab=<?= e($tab) ?>&export=pdf" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i> PDF</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-info">
            <h3><?= money($totalRevenue) ?></h3>
            <p>Total Revenue</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
            <h3><?= money($totalProfit) ?></h3>
            <p>Total Profit</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3><?= (int) $totalTransactions ?></h3>
            <p>Transactions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-box"></i></div>
        <div class="stat-info">
            <h3><?= count($inventoryData) ?></h3>
            <p>Products</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Select Period</h2>
        <div class="d-flex gap-8">
            <a href="?page=reports&period=today&tab=<?= e($tab) ?>" class="btn btn-sm <?= $period === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
            <a href="?page=reports&period=week&tab=<?= e($tab) ?>" class="btn btn-sm <?= $period === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
            <a href="?page=reports&period=month&tab=<?= e($tab) ?>" class="btn btn-sm <?= $period === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="tabs">
        <button class="tab <?= $tab === 'sales' ? 'active' : '' ?>" onclick="window.location.href='?page=reports&period=<?= e($period) ?>&tab=sales'">Sales</button>
        <button class="tab <?= $tab === 'products' ? 'active' : '' ?>" onclick="window.location.href='?page=reports&period=<?= e($period) ?>&tab=products'">By Product</button>
        <button class="tab <?= $tab === 'cashiers' ? 'active' : '' ?>" onclick="window.location.href='?page=reports&period=<?= e($period) ?>&tab=cashiers'">By Cashier</button>
        <button class="tab <?= $tab === 'profit' ? 'active' : '' ?>" onclick="window.location.href='?page=reports&period=<?= e($period) ?>&tab=profit'">Profit</button>
        <button class="tab <?= $tab === 'inventory' ? 'active' : '' ?>" onclick="window.location.href='?page=reports&period=<?= e($period) ?>&tab=inventory'">Inventory</button>
    </div>

    <?php if ($tab === 'sales'): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Date</th><th>Transactions</th><th>Total</th><th>Tax</th><th>Discount</th></tr></thead>
                <tbody>
                    <?php if (empty($salesData)): ?>
                        <tr><td colspan="5" class="text-center p-40 text-muted">No sales data for this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($salesData as $row): ?>
                        <tr>
                            <td><?= e($row['day']) ?></td>
                            <td><?= (int) $row['transactions'] ?></td>
                            <td><?= money((float) $row['total']) ?></td>
                            <td><?= money((float) $row['tax']) ?></td>
                            <td><?= money((float) $row['discount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($tab === 'products'): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Product</th><th>Qty Sold</th><th>Total</th><th>Cost</th><th>Profit</th></tr></thead>
                <tbody>
                    <?php if (empty($productData)): ?>
                        <tr><td colspan="5" class="text-center p-40 text-muted">No product sales for this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($productData as $row): ?>
                        <?php $profit = (float) ($row['total'] ?? 0) - (float) ($row['cost'] ?? 0); ?>
                        <tr>
                            <td><?= e($row['product_name']) ?></td>
                            <td><?= (int) $row['qty'] ?></td>
                            <td><?= money((float) $row['total']) ?></td>
                            <td><?= money((float) ($row['cost'] ?? 0)) ?></td>
                            <td><?= money($profit) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($tab === 'cashiers'): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Cashier</th><th>Transactions</th><th>Total</th></tr></thead>
                <tbody>
                    <?php if (empty($cashierData)): ?>
                        <tr><td colspan="3" class="text-center p-40 text-muted">No data for this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cashierData as $row): ?>
                        <tr>
                            <td><?= e($row['cashier_name']) ?></td>
                            <td><?= (int) $row['transactions'] ?></td>
                            <td><?= money((float) $row['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($tab === 'profit'): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Date</th><th>Revenue</th><th>Cost</th><th>Profit</th><th>Margin</th></tr></thead>
                <tbody>
                    <?php if (empty($profitData)): ?>
                        <tr><td colspan="5" class="text-center p-40 text-muted">No data for this period.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($profitData as $row): ?>
                        <?php
                            $rev = (float) ($row['revenue'] ?? 0);
                            $cst = (float) ($row['cost'] ?? 0);
                            $prf = (float) ($row['profit'] ?? 0);
                            $margin = $rev > 0 ? round(($prf / $rev) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= e($row['day']) ?></td>
                            <td><?= money($rev) ?></td>
                            <td><?= money($cst) ?></td>
                            <td><strong><?= money($prf) ?></strong></td>
                            <td><?= $margin ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($tab === 'inventory'): ?>
        <div class="table-container">
            <table>
                <thead><tr><th>Product</th><th>Stock</th><th>Price</th><th>Cost</th><th>Stock Value</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($inventoryData as $row): ?>
                        <?php $stockVal = (float) $row['stock_quantity'] * (float) $row['cost_price']; ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= (int) $row['stock_quantity'] ?></td>
                            <td><?= money((float) $row['price']) ?></td>
                            <td><?= money((float) $row['cost_price']) ?></td>
                            <td><?= money($stockVal) ?></td>
                            <td>
                                <?php if ((int) $row['stock_quantity'] === 0): ?>
                                    <span class="badge badge-danger">Out</span>
                                <?php elseif ((int) $row['stock_quantity'] <= (int) $row['low_stock_threshold']): ?>
                                    <span class="badge badge-warning">Low</span>
                                <?php else: ?>
                                    <span class="badge badge-success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
