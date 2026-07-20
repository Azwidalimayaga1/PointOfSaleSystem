<?php

declare(strict_types=1);

// Coupon management page - only super_admin and store_admin can access
$storeId = activeStoreId();
$isSuper = isSuperAdmin();

// Filters
$filterStore = isset($_GET['store_id']) ? (int) $_GET['store_id'] : ($isSuper ? 0 : $storeId);
$filterStatus = $_GET['status'] ?? '';
$filterType = $_GET['discount_type'] ?? '';
$filterFrom = $_GET['from_date'] ?? '';
$filterTo = $_GET['to_date'] ?? '';
$filterSearch = $_GET['search'] ?? '';

$filters = [];
if ($filterStore > 0) $filters['store_id'] = $filterStore;
if ($filterStatus) $filters['status'] = $filterStatus;
if ($filterType) $filters['discount_type'] = $filterType;
if ($filterFrom) $filters['from_date'] = $filterFrom;
if ($filterTo) $filters['to_date'] = $filterTo;
if ($filterSearch) $filters['search'] = $filterSearch;

$coupons = getCoupons($db, $filters);
$stores = $isSuper ? getStores($db) : [];

// Stats
$totalCoupons = count($coupons);
$activeCoupons = count(array_filter($coupons, fn($c) => $c['status'] === 'active'));
$totalUsage = array_sum(array_map(fn($c) => (int) ($c['used_count'] ?? 0), $coupons));
?>

<div class="page-header">
    <h1><i class="fas fa-tags"></i> Discount Coupons</h1>
    <div class="d-flex gap-8">
        <a href="index.php?page=coupon-form" class="btn btn-success"><i class="fas fa-plus"></i> New Coupon</a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-16">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-tag"></i></div>
        <div class="stat-info">
            <h3><?= $totalCoupons ?></h3>
            <p>Total Coupons</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?= $activeCoupons ?></h3>
            <p>Active Coupons</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-percent"></i></div>
        <div class="stat-info">
            <h3><?= $activeCoupons > 0 ? round(($activeCoupons / max($totalCoupons, 1)) * 100) : 0 ?>%</h3>
            <p>Active Rate</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-info">
            <h3><?= $totalUsage ?></h3>
            <p>Total Uses</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-16">
    <div class="card-header">
        <h2><i class="fas fa-filter"></i> Filters</h2>
    </div>
    <form method="get" class="d-flex gap-10 flex-wrap align-end">
        <input type="hidden" name="page" value="coupons">
        <div class="form-group m-0">
            <label class="fs-11">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Code or name..." style="padding:7px 10px;min-width:160px" value="<?= e($filterSearch) ?>">
        </div>
        <?php if ($isSuper): ?>
        <div class="form-group m-0">
            <label class="fs-11">Store</label>
            <select name="store_id" class="form-control" style="padding:7px 10px;min-width:140px">
                <option value="0">All Stores</option>
                <?php foreach ($stores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $filterStore === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group m-0">
            <label class="fs-11">Status</label>
            <select name="status" class="form-control" style="padding:7px 10px;min-width:120px">
                <option value="">All Status</option>
                <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
            </select>
        </div>
        <div class="form-group m-0">
            <label class="fs-11">Discount Type</label>
            <select name="discount_type" class="form-control" style="padding:7px 10px;min-width:120px">
                <option value="">All Types</option>
                <option value="percentage" <?= $filterType === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                <option value="fixed" <?= $filterType === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
            </select>
        </div>
        <div class="form-group m-0">
            <label class="fs-11">From</label>
            <input type="date" name="from_date" class="form-control" style="padding:7px 10px;width:140px" value="<?= e($filterFrom) ?>">
        </div>
        <div class="form-group m-0">
            <label class="fs-11">To</label>
            <input type="date" name="to_date" class="form-control" style="padding:7px 10px;width:140px" value="<?= e($filterTo) ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="index.php?page=coupons" class="btn btn-outline btn-sm"><i class="fas fa-undo"></i> Reset</a>
    </form>
</div>

<!-- Coupons Table -->
<div class="card">
    <?php if (empty($coupons)): ?>
    <div class="empty-state">
        <i class="fas fa-tags"></i>
        <p>No discount coupons found. Create your first coupon to start promoting sales!</p>
        <a href="index.php?page=coupon-form" class="btn btn-success"><i class="fas fa-plus"></i> Create Coupon</a>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Value</th>
                    <?php if ($isSuper): ?><th>Store</th><?php endif; ?>
                    <th>Start</th>
                    <th>End</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coupons as $c):
                    $isExpired = $c['end_date'] && $c['end_date'] < date('Y-m-d');
                    $displayStatus = $c['status'];
                    if ($isExpired && $displayStatus === 'active') {
                        $displayStatus = 'expired';
                    }
                    $usageLimit = (int) ($c['usage_limit'] ?? 0);
                    $usedCount = (int) ($c['used_count'] ?? 0);
                    $usageFull = $usageLimit > 0 && $usedCount >= $usageLimit;
                ?>
                <tr>
                    <td><strong class="fs-14"><?= e($c['code']) ?></strong></td>
                    <td><?= e($c['name']) ?></td>
                    <td><span class="badge badge-<?= $c['discount_type'] === 'percentage' ? 'info' : 'warning' ?>"><?= e($c['discount_type']) ?></span></td>
                    <td>
                        <?php if ($c['discount_type'] === 'percentage'): ?>
                            <?= (float) $c['discount_value'] ?>%
                        <?php else: ?>
                            <?= money((float) $c['discount_value']) ?>
                        <?php endif; ?>
                        <?php if ((float) ($c['minimum_spend'] ?? 0) > 0): ?>
                            <br><span class="fs-11 text-muted">Min: <?= money((float) $c['minimum_spend']) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isSuper): ?>
                    <td><?= e($c['store_name'] ?? 'All Stores') ?></td>
                    <?php endif; ?>
                    <td class="fs-11"><?= $c['start_date'] ? e(date('Y-m-d', strtotime($c['start_date']))) : '&mdash;' ?></td>
                    <td class="fs-11"><?= $c['end_date'] ? e(date('Y-m-d', strtotime($c['end_date']))) : '&mdash;' ?></td>
                    <td>
                        <?= $usedCount ?><?= $usageLimit > 0 ? ' / ' . $usageLimit : '' ?>
                        <?php if ($usageFull): ?>
                            <br><span class="badge badge-danger fs-10">Full</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($displayStatus === 'active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php elseif ($displayStatus === 'inactive'): ?>
                            <span class="badge badge-gray">Inactive</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Expired</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-4">
                            <a href="index.php?page=coupon-form&id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                            <?php if ($displayStatus === 'active'): ?>
                            <button class="btn btn-sm btn-warning" onclick="toggleCouponStatus(<?= (int) $c['id'] ?>, 'inactive')" title="Deactivate"><i class="fas fa-pause"></i></button>
                            <?php elseif ($displayStatus === 'inactive'): ?>
                            <button class="btn btn-sm btn-success" onclick="toggleCouponStatus(<?= (int) $c['id'] ?>, 'active')" title="Activate"><i class="fas fa-play"></i></button>
                            <?php endif; ?>
                            <?php if ($usedCount === 0): ?>
                            <button class="btn btn-sm btn-danger" onclick="deleteCoupon(<?= (int) $c['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleCouponStatus(id, newStatus) {
    if (!confirm('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this coupon?')) return;
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('coupon_id', id.toString());
    formData.append('new_status', newStatus);
    formData.append('_csrf', '<?= csrf_token() ?>');
    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast(d.message, 'success'); location.reload(); }
            else { showToast(d.message, 'danger'); }
        })
        .catch(() => showToast('An error occurred.', 'danger'));
}

function deleteCoupon(id) {
    if (!confirm('Delete this coupon? This cannot be undone.')) return;
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('coupon_id', id.toString());
    formData.append('_csrf', '<?= csrf_token() ?>');
    fetch('index.php?page=sales&action=ajax', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast(d.message, 'success'); location.reload(); }
            else { showToast(d.message, 'danger'); }
        })
        .catch(() => showToast('An error occurred.', 'danger'));
}
</script>
