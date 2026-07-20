<?php

declare(strict_types=1);

$userRole = userRole();
$isSystemAdmin = isSuperAdmin();

// Handle approve/reject/delete/activate for system admin via POST only
if ($isSystemAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Invalid security token. Please refresh and try again.'];
        redirect('index.php?page=stores');
    }

    if (isset($_POST['approve'])) {
        $sid = (int) $_POST['approve'];
        $store = getStore($db, $sid);
        if ($store && $store['status'] === 'pending') {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE stores SET status = 'active' WHERE id = ?")->execute([$sid]);
                $db->prepare("UPDATE users SET status = 'active' WHERE store_id = ? AND role != 'super_admin'")->execute([$sid]);
                $db->commit();
                logAction($db, 'approve_store', 'store', $sid, 'Approved pending store: ' . $store['name']);
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Store "' . $store['name'] . '" approved and activated.'];
            } catch (Exception $e) {
                $db->rollBack();
                logAction($db, 'approve_store_failed', 'store', $sid, 'Failed to approve store: ' . $e->getMessage());
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Failed to approve store.'];
            }
        }
        redirect('index.php?page=stores');
    }

    if (isset($_POST['reject'])) {
        $sid = (int) $_POST['reject'];
        $store = getStore($db, $sid);
        if ($store && $store['status'] === 'pending') {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE stores SET status = 'inactive' WHERE id = ?")->execute([$sid]);
                $db->prepare("UPDATE users SET status = 'inactive' WHERE store_id = ? AND role != 'super_admin'")->execute([$sid]);
                $db->commit();
                logAction($db, 'reject_store', 'store', $sid, 'Rejected pending store: ' . $store['name']);
                $_SESSION['pos_flash'] = ['type' => 'warning', 'message' => 'Store "' . $store['name'] . '" rejected and deactivated.'];
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Failed to reject store.'];
            }
        }
        redirect('index.php?page=stores');
    }

    if (isset($_POST['delete'])) {
        $sid = (int) $_POST['delete'];
        $delStore = getStore($db, $sid);
        if ($sid === ACTIVE_STORE_ID) {
            $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Cannot delete the currently active store.'];
        } elseif ($delStore) {
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM stores WHERE id = ?")->execute([$sid]);
                $db->prepare("DELETE FROM users WHERE store_id = ? AND role != 'super_admin'")->execute([$sid]);
                $db->commit();
                logAction($db, 'delete_store', 'store', $sid, 'Deleted store: ' . $delStore['name']);
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Store "' . $delStore['name'] . '" deleted.'];
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Failed to delete store.'];
            }
        }
        redirect('index.php?page=stores');
    }

    if (isset($_POST['activate'])) {
        $sid = (int) $_POST['activate'];
        $store = getStore($db, $sid);
        if ($store && in_array($store['status'], ['active', 'inactive', 'pending'], true)) {
            if ($store['status'] !== 'active') {
                $db->prepare("UPDATE stores SET status = 'active' WHERE id = ?")->execute([$sid]);
                logAction($db, 'activate_store', 'store', $sid, 'Activated store: ' . $store['name']);
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Store "' . $store['name'] . '" activated successfully.'];
            }
            $_SESSION['store_id'] = $sid;
            logAction($db, 'switch_store', 'store', $sid, 'Switched to store: ' . $store['name']);
            $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Switched to store: ' . $store['name']];
        }
        redirect('index.php?page=stores');
    }
}

// Store admin can only see their own store
if (!$isSystemAdmin) {
    $stores = [getStore($db, activeStoreId())];
    $stores = array_filter($stores);
} else {
    $stores = getStores($db);
}
?>
<?php $flash = $_SESSION['pos_flash'] ?? null; unset($_SESSION['pos_flash']);

$pendingCount = 0;
$activeCount = 0;
$inactiveCount = 0;
$storeChartData = [];
if ($isSystemAdmin) {
    $stmt = $db->query("SELECT COUNT(*) FROM stores WHERE status = 'pending'");
    $pendingCount = (int) $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM stores WHERE status = 'active'");
    $activeCount = (int) $stmt->fetchColumn();
    $stmt = $db->query("SELECT COUNT(*) FROM stores WHERE status = 'inactive'");
    $inactiveCount = (int) $stmt->fetchColumn();
    foreach ($stores as $s) {
        $storeChartData[$s['id']] = getStoreDashboardData($db, (int) $s['id']);
    }
}
?>
<div class="page-header">
    <?php if ($pendingCount > 0 && $isSystemAdmin): ?>
        <span class="badge badge-warning fs-12 ml-8" style="vertical-align:middle"><?= $pendingCount ?> pending</span>
    <?php endif; ?>
    <?php if ($isSystemAdmin): ?>
    <a href="index.php?page=store-form" class="btn btn-primary"><i class="fas fa-plus"></i> Add Store</a>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
    <div id="flash-message" data-flash='<?= e(json_encode(['type' => $flash['type'], 'message' => $flash['message']])) ?>'></div>
<?php endif; ?>

<!-- Stores Table -->
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Currency</th>
                    <th>Tax</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $s): ?>
                    <?php $isPending = ($s['status'] ?? '') === 'pending'; ?>
                    <tr>
                        <td>
                            <strong><?= e($s['name']) ?></strong>
                            <?php if ((int) $s['id'] === ACTIVE_STORE_ID): ?>
                                <span class="badge badge-primary ml-6">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($s['address'] ?? '—') ?></td>
                        <td><?= e($s['contact'] ?? '—') ?></td>
                        <td><?= e($s['currency'] ?? 'R') ?></td>
                        <td><?= (float) ($s['tax_rate'] ?? 15) ?>%</td>
                        <td>
                            <span class="badge <?= $isPending ? 'badge-warning' : ($s['status'] === 'active' ? 'badge-success' : 'badge-danger') ?>">
                                <?= e(ucfirst($s['status'] ?? 'active')) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-4 flex-nowrap">
                                <?php if ($isPending && $isSystemAdmin): ?>
                                    <form method="post" action="index.php?page=stores" style="display:inline" onsubmit="return confirm('Approve this store?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="approve" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="post" action="index.php?page=stores" style="display:inline" onsubmit="return confirm('Reject this store?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="reject" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button>
                                    </form>
                                <?php elseif ((int) $s['id'] !== ACTIVE_STORE_ID && $s['status'] === 'active' && $isSystemAdmin): ?>
                                    <form method="post" action="index.php?page=stores" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="activate" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Switch</button>
                                    </form>
                                <?php elseif ($s['status'] === 'inactive' && $isSystemAdmin): ?>
                                    <form method="post" action="index.php?page=stores" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="activate" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Reactivate</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isSystemAdmin || (int) $s['id'] === activeStoreId()): ?>
                                <a href="index.php?page=store-form&id=<?= (int) $s['id'] ?>" class="btn btn-sm btn-ghost" title="Edit store"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if ((int) $s['id'] !== ACTIVE_STORE_ID && $isSystemAdmin): ?>
                                    <form method="post" action="index.php?page=stores" style="display:inline" onsubmit="return confirm('Delete this store? This cannot be undone.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost text-danger" title="Delete store"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($stores)): ?>
                    <tr><td colspan="7" class="text-center py-20 text-muted">No stores found.
                        <?php if ($isSystemAdmin): ?>
                        <br><a href="index.php?page=store-form" class="btn btn-sm btn-primary mt-8">Add Store</a>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($isSystemAdmin && !empty($stores)): ?>
<!-- Stats Cards -->
<div class="stats-grid mt-20">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-store"></i></div>
        <div class="stat-info">
            <h3><?= count($stores) ?></h3>
            <p>Total Stores</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?= $activeCount ?></h3>
            <p>Active</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <h3><?= $pendingCount ?></h3>
            <p>Pending</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-ban"></i></div>
        <div class="stat-info">
            <h3><?= $inactiveCount ?></h3>
            <p>Inactive</p>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="grid-2 mt-20">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-bar"></i> Today's Sales by Store</h2>
        </div>
        <?php
        $hasSalesData = array_sum(array_map(fn($s) => (float) ($storeChartData[$s['id']]['today_sales'] ?? 0), $stores)) > 0;
        ?>
        <?php if (!$hasSalesData): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <p>No sales recorded for any store today.</p>
        </div>
        <?php else: ?>
        <div style="height:260px">
            <canvas id="salesByStoreChart" class="w-full h-full"></canvas>
        </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chart-pie"></i> Store Status</h2>
        </div>
        <?php if ($activeCount === 0 && $pendingCount === 0 && $inactiveCount === 0): ?>
        <div class="empty-state">
            <i class="fas fa-chart-pie"></i>
            <p>No stores configured yet.</p>
        </div>
        <?php else: ?>
        <div class="d-flex justify-center" style="height:260px">
            <canvas id="statusChart" style="max-width:260px;height:100%"></canvas>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-20">
    <div class="card-header">
        <h2><i class="fas fa-boxes"></i> Products & Stock by Store</h2>
    </div>
    <?php if (empty($stores)): ?>
    <div class="empty-state">
        <i class="fas fa-boxes"></i>
        <p>No store data available yet.</p>
    </div>
    <?php else: ?>
    <div style="height:260px">
        <canvas id="productsByStoreChart" class="w-full h-full"></canvas>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($stores) && $hasSalesData): ?>
    var salesCtx = document.getElementById('salesByStoreChart');
    if (salesCtx) {
        new Chart(salesCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php foreach ($stores as $s): ?>'<?= e($s['name']) ?>',<?php endforeach; ?>],
                datasets: [{
                    label: "Today's Sales",
                    data: [<?php foreach ($stores as $s): ?><?= (float) ($storeChartData[$s['id']]['today_sales'] ?? 0) ?>,<?php endforeach; ?>],
                    backgroundColor: [<?php foreach ($stores as $s): ?>'<?= (int) $s['id'] === ACTIVE_STORE_ID ? '#3b82f6' : '#10b981' ?>',<?php endforeach; ?>],
                    borderRadius: 4,
                }, {
                    label: 'Transactions',
                    data: [<?php foreach ($stores as $s): ?><?= (int) ($storeChartData[$s['id']]['today_transactions'] ?? 0) ?>,<?php endforeach; ?>],
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
    <?php endif; ?>

    <?php if (!empty($stores) && ($activeCount > 0 || $pendingCount > 0 || $inactiveCount > 0)): ?>
    var statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Pending', 'Inactive'],
                datasets: [{
                    data: [<?= (int) $activeCount ?>, <?= (int) $pendingCount ?>, <?= (int) $inactiveCount ?>],
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
    <?php endif; ?>

    <?php if (!empty($stores)): ?>
    var prodCtx = document.getElementById('productsByStoreChart');
    if (prodCtx) {
        new Chart(prodCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [<?php foreach ($stores as $s): ?>'<?= e($s['name']) ?>',<?php endforeach; ?>],
                datasets: [{
                    label: 'Products',
                    data: [<?php foreach ($stores as $s): ?><?= (int) ($storeChartData[$s['id']]['product_count'] ?? 0) ?>,<?php endforeach; ?>],
                    backgroundColor: '#8b5cf6',
                    borderRadius: 4,
                }, {
                    label: 'Low Stock',
                    data: [<?php foreach ($stores as $s): ?><?= (int) ($storeChartData[$s['id']]['low_stock'] ?? 0) ?>,<?php endforeach; ?>],
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
<?php endif; ?>
