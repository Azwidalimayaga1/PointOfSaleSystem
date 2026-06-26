<?php

declare(strict_types=1);

$userRole = $_SESSION['user']['role'] ?? '';
$isSystemAdmin = $userRole === 'admin';
$storeIdFilter = $isSystemAdmin ? '(store_id = ? OR store_id IS NULL)' : 'store_id = ?';

// Handle approve/reject/delete via POST only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Invalid security token. Please refresh and try again.'];
        redirect('index.php?page=users');
    }

    if (isset($_POST['approve'])) {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND $storeIdFilter");
        $stmt->execute([(int) $_POST['approve'], activeStoreId()]);
        logAction($db, 'user_approve', 'user', (int) $_POST['approve'], 'Approved user ID: ' . (int) $_POST['approve']);
        redirect('index.php?page=users');
    }
    if (isset($_POST['reject'])) {
        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND $storeIdFilter");
        $stmt->execute([(int) $_POST['reject'], activeStoreId()]);
        logAction($db, 'user_reject', 'user', (int) $_POST['reject'], 'Rejected user ID: ' . (int) $_POST['reject']);
        redirect('index.php?page=users');
    }
    if (isset($_POST['delete'])) {
        $userId = (int) $_POST['delete'];
        $targetUser = $db->prepare("SELECT * FROM users WHERE id = ? AND $storeIdFilter");
        $targetUser->execute([$userId, activeStoreId()]);
        $targetUser = $targetUser->fetch();
        if ($targetUser) {
            if ((int) $targetUser['id'] === (int) ($_SESSION['user']['id'] ?? 0)) {
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Cannot delete your own account.'];
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND $storeIdFilter");
                $stmt->execute([$userId, activeStoreId()]);
                logAction($db, 'user_delete', 'user', $userId, 'Deleted user: ' . $targetUser['username'] . ' (' . $targetUser['role'] . ')');
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'User "' . $targetUser['username'] . '" deleted.'];
            }
        }
        redirect('index.php?page=users');
    }
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
    <div class="d-flex gap-8 align-center">
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-danger fs-14" style="padding:6px 14px">
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
                    <tr class="<?= $u['status'] === 'pending' ? 'bg-warning-light' : '' ?>">
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
                                <form method="post" action="index.php?page=users" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="approve" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <form method="post" action="index.php?page=users" style="display:inline" onsubmit="return confirm('Reject this user?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reject" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            <?php else: ?>
                                <a href="index.php?page=user-form&id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-primary" title="Edit user"><i class="fas fa-edit"></i></a>
                                <?php if ((int) $u['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
                                <form method="post" action="index.php?page=users" style="display:inline" onsubmit="return confirm('Delete user <?= e($u['username']) ?>? This cannot be undone.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete user"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
