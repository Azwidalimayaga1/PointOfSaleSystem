<?php

declare(strict_types=1);

$userRole = $_SESSION['user']['role'] ?? '';
$isSystemAdmin = $userRole === 'admin';
$storeIdFilter = $isSystemAdmin ? '(store_id = ? OR store_id IS NULL)' : 'store_id = ?';

// Handle approve/reject
if (isset($_GET['approve'])) {
    $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND $storeIdFilter");
    $stmt->execute([(int) $_GET['approve'], activeStoreId()]);
    logAction($db, 'user_approve', 'user', (int) $_GET['approve'], 'Approved user ID: ' . (int) $_GET['approve']);
    redirect('index.php?page=users');
}
if (isset($_GET['reject'])) {
    $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND $storeIdFilter");
    $stmt->execute([(int) $_GET['reject'], activeStoreId()]);
    logAction($db, 'user_reject', 'user', (int) $_GET['reject'], 'Rejected user ID: ' . (int) $_GET['reject']);
    redirect('index.php?page=users');
}

$users = $db->prepare("SELECT id, username, full_name, role, status, created_at FROM users WHERE $storeIdFilter ORDER BY status = 'pending' DESC, role, full_name");
$users->execute([activeStoreId()]);
$users = $users->fetchAll();
$pendingCount = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending' AND $storeIdFilter");
$pendingCount->execute([activeStoreId()]);
$pendingCount = $pendingCount->fetchColumn();
?>
<div class="page-header">
    <h1><i class="fas fa-users"></i> User Management</h1>
    <div style="display:flex;gap:8px;align-items:center">
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-danger" style="font-size:14px;padding:6px 14px">
                <i class="fas fa-clock"></i> <?= (int) $pendingCount ?> pending approval
            </span>
        <?php endif; ?>
        <a href="index.php?page=user-form" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</a>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="<?= $u['status'] === 'pending' ? 'background:var(--bg-warning-light)' : '' ?>">
                        <td><strong><?= e($u['username']) ?></strong></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-danger' : ($u['role'] === 'manager' ? 'badge-warning' : ($u['role'] === 'store_admin' ? 'badge-primary' : 'badge-info')) ?>">
                                <?= e($u['role'] === 'store_admin' ? 'Store Admin' : ucfirst($u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'pending'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php elseif ($u['status'] === 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-gray"><?= e(ucfirst($u['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
                        <td>
                            <?php if ($u['status'] === 'pending'): ?>
                                <a href="?page=users&approve=<?= (int) $u['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</a>
                                <a href="?page=users&reject=<?= (int) $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this user?')"><i class="fas fa-times"></i> Reject</a>
                            <?php else: ?>
                                <a href="index.php?page=user-form&id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
