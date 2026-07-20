<?php declare(strict_types=1);

$search = $_GET['search'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$entityFilter = $_GET['entity_type'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$dir = $_GET['dir'] ?? 'DESC';
$page = max(1, (int) ($_GET['page'] ?? 1));

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportAuditLogsCsv($db, $search, $actionFilter, $entityFilter, $from, $to);
}

// Handle PDF export as CSV (browser-friendly)
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    exportAuditLogsCsv($db, $search, $actionFilter, $entityFilter, $from, $to);
}

$result = getAuditLogs($db, $search, $actionFilter, $entityFilter, $from, $to, $sort, $dir, $page, 50);
$logs = $result['data'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $result['perPage']));

$actions = getAuditLogActions($db);
$entityTypes = getAuditLogEntityTypes($db);

$actionLabels = [
    'login' => ['Login', 'badge-success'],
    'logout' => ['Logout', 'badge-gray'],
    'product_create' => ['Product Created', 'badge-primary'],
    'product_update' => ['Product Updated', 'badge-info'],
    'product_delete' => ['Product Deleted', 'badge-danger'],
    'stock_adjustment' => ['Stock Adjustment', 'badge-warning'],
    'settings_update' => ['Settings Updated', 'badge-primary'],
    'user_create' => ['User Created', 'badge-success'],
    'user_update' => ['User Updated', 'badge-info'],
    'user_approve' => ['User Approved', 'badge-success'],
    'user_reject' => ['User Rejected', 'badge-danger'],
];
?>
<div class="page-header">
    <div class="d-flex gap-8">
        <a href="?page=audit-logs&export=csv&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>" class="btn btn-sm btn-success"><i class="fas fa-file-csv"></i> Export CSV</a>
        <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf"></i> Export PDF</button>
    </div>
</div>

<div class="card mb-16">
    <form method="get" class="search-bar flex-wrap">
        <input type="hidden" name="page" value="audit-logs">
        <input type="text" name="search" class="form-control" placeholder="Search user, action, details..." value="<?= e($search) ?>" style="min-width:180px" aria-label="Search audit logs">
        <select name="action" class="form-control" aria-label="Filter by action">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e($actionLabels[$a][0] ?? ucfirst(str_replace('_', ' ', $a))) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="entity_type" class="form-control" aria-label="Filter by entity type">
            <option value="">All Entity Types</option>
            <?php foreach ($entityTypes as $et): ?>
            <option value="<?= e($et) ?>" <?= $entityFilter === $et ? 'selected' : '' ?>><?= e(ucfirst($et)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>" aria-label="From date">
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>" aria-label="To date">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
        <a href="?page=audit-logs" class="btn btn-outline"><i class="fas fa-times"></i> Clear</a>
    </form>
</div>

<div class="card">
    <div class="flex-between mb-12">
        <span class="fs-14 text-muted"><?= $total ?> total entries (page <?= $page ?> of <?= $totalPages ?>)</span>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th><a href="?page=audit-logs&sort=created_at&dir=<?= $sort === 'created_at' && $dir === 'DESC' ? 'ASC' : 'DESC' ?>&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">Date <?= $sort === 'created_at' ? ($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
                    <th><a href="?page=audit-logs&sort=user_name&dir=<?= $sort === 'user_name' && $dir === 'DESC' ? 'ASC' : 'DESC' ?>&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">User <?= $sort === 'user_name' ? ($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
                    <th>Role</th>
                    <th>Store</th>
                    <th><a href="?page=audit-logs&sort=action&dir=<?= $sort === 'action' && $dir === 'DESC' ? 'ASC' : 'DESC' ?>&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">Action <?= $sort === 'action' ? ($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-center p-40 text-muted">No audit log entries found.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="nowrap"><?= e(date('Y-m-d H:i', strtotime($log['created_at']))) ?></td>
                    <td><strong><?= e($log['user_name']) ?></strong></td>
                    <td><span class="badge badge-info"><?= e(ucfirst($log['user_role'])) ?></span></td>
                    <td><?= e($log['store_name'] ?? '—') ?></td>
                    <td>
                        <?php $label = $actionLabels[$log['action']] ?? [ucfirst(str_replace('_', ' ', $log['action'])), 'badge-info']; ?>
                        <span class="badge <?= $label[1] ?>"><?= e($label[0]) ?></span>
                    </td>
                    <td class="text-truncate" style="max-width:350px" title="<?= e($log['details'] ?? '') ?>">
                        <?php if ($log['entity_type'] && $log['entity_id']): ?>
                            <a href="?page=audit-logs&entity_type=<?= e($log['entity_type']) ?>&search=<?= e($log['entity_id']) ?>" class="fs-11 text-muted">[<?= e($log['entity_type']) ?> #<?= (int) $log['entity_id'] ?>]</a>
                        <?php endif; ?>
                        <?= e($log['details'] ?? '') ?>
                    </td>
                    <td class="fs-12 text-muted" style="font-family:monospace"><?= e($log['ip_address'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-center gap-6 mt-16 flex-wrap">
        <?php if ($page > 1): ?>
        <a href="?page=audit-logs&page=<?= $page - 1 ?>&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&sort=<?= e($sort) ?>&dir=<?= e($dir) ?>" class="btn btn-sm btn-outline"><i class="fas fa-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
        <a href="?page=audit-logs&page=<?= $i ?>&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&sort=<?= e($sort) ?>&dir=<?= e($dir) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=audit-logs&page=<?= $page + 1 ?>&search=<?= e($search) ?>&action=<?= e($actionFilter) ?>&entity_type=<?= e($entityFilter) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>&sort=<?= e($sort) ?>&dir=<?= e($dir) ?>" class="btn btn-sm btn-outline">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
