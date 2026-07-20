<?php

declare(strict_types=1);

$userRole = userRole();
$isSystemAdmin = isSuperAdmin();

// Handle approve/reject/delete via POST only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Invalid security token. Please refresh and try again.'];
        redirect('index.php?page=users');
    }

    if (isset($_POST['approve'])) {
        if (isSuperAdmin()) {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([(int) $_POST['approve']]);
        } else {
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND store_id = ?");
            $stmt->execute([(int) $_POST['approve'], currentUserStoreId()]);
        }
        logAction($db, 'user_approve', 'user', (int) $_POST['approve'], 'Approved user ID: ' . (int) $_POST['approve']);
        redirect('index.php?page=users');
    }
    if (isset($_POST['reject'])) {
        if (isSuperAdmin()) {
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([(int) $_POST['reject']]);
        } else {
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND store_id = ?");
            $stmt->execute([(int) $_POST['reject'], currentUserStoreId()]);
        }
        logAction($db, 'user_reject', 'user', (int) $_POST['reject'], 'Rejected user ID: ' . (int) $_POST['reject']);
        redirect('index.php?page=users');
    }
    if (isset($_POST['delete'])) {
        $userId = (int) $_POST['delete'];
        if (isSuperAdmin()) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND store_id = ?");
            $stmt->execute([$userId, currentUserStoreId()]);
        }
        $targetUser = $stmt->fetch();
        if ($targetUser) {
            if ((int) $targetUser['id'] === CURRENT_USER_ID) {
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Cannot delete your own account.'];
            } else {
                if (isSuperAdmin()) {
                    $del = $db->prepare("DELETE FROM users WHERE id = ?");
                    $del->execute([$userId]);
                } else {
                    $del = $db->prepare("DELETE FROM users WHERE id = ? AND store_id = ?");
                    $del->execute([$userId, currentUserStoreId()]);
                }
                logAction($db, 'user_delete', 'user', $userId, 'Deleted user: ' . $targetUser['username'] . ' (' . $targetUser['role'] . ')');
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'User "' . $targetUser['username'] . '" deleted.'];
            }
        }
        redirect('index.php?page=users');
    }
}

// Build query based on role
if (isSuperAdmin()) {
    $users = $db->query("SELECT id, username, full_name, email, role, store_id, status, created_at FROM users ORDER BY status = 'pending' DESC, role, full_name")->fetchAll();
    $pendingCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
} else {
    $stmt = $db->prepare("SELECT id, username, full_name, email, role, store_id, status, created_at FROM users WHERE store_id = ? ORDER BY status = 'pending' DESC, role, full_name");
    $stmt->execute([currentUserStoreId()]);
    $users = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending' AND store_id = ?");
    $stmt->execute([currentUserStoreId()]);
    $pendingCount = (int) $stmt->fetchColumn();
}

// Get stores list for display
$stores = [];
if (isSuperAdmin()) {
    $stores = $db->query("SELECT id, name FROM stores ORDER BY name")->fetchAll();
    $storeMap = [];
    foreach ($stores as $s) {
        $storeMap[$s['id']] = $s['name'];
    }
}

// Super admin count warning
$superAdminCount = countSuperAdmins($db);
$superAdminLimitReached = $superAdminCount >= 3;
?>
<div class="page-header">
    <div class="d-flex gap-8 align-center">
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-danger fs-14" style="padding:6px 14px">
                <i class="fas fa-clock"></i> <?= (int) $pendingCount ?> pending approval
            </span>
        <?php endif; ?>
        <a href="index.php?page=user-form" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</a>
    </div>
</div>

<?php if ($superAdminLimitReached && isSuperAdmin()): ?>
<div class="alert alert-warning">
    <i class="fas fa-info-circle"></i>
    The system currently has <strong><?= $superAdminCount ?></strong> active Super Admins (maximum of 3). You cannot create additional Super Admin accounts unless you deactivate an existing one.
</div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Store</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $userStoreId = $u['store_id'] ? (int) $u['store_id'] : null; ?>
                    <tr class="<?= $u['status'] === 'pending' ? 'bg-warning-light' : '' ?>">
                        <td><strong><?= e($u['username']) ?></strong></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'super_admin' ? 'badge-danger' : ($u['role'] === 'store_admin' ? 'badge-primary' : ($u['role'] === 'manager' ? 'badge-warning' : 'badge-info')) ?>">
                                <?= $u['role'] === 'super_admin' ? 'Super Admin' : ($u['role'] === 'store_admin' ? 'Store Admin' : ucfirst($u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'super_admin'): ?>
                                <span class="text-muted">All Stores</span>
                            <?php elseif ($userStoreId && isset($storeMap[$userStoreId])): ?>
                                <span class="badge badge-gray"><?= e($storeMap[$userStoreId]) ?></span>
                            <?php elseif ($userStoreId): ?>
                                <?php
                                $sStmt = $db->prepare("SELECT name FROM stores WHERE id = ?");
                                $sStmt->execute([$userStoreId]);
                                $sName = $sStmt->fetchColumn();
                                ?>
                                <span class="badge badge-gray"><?= e($sName ?: 'Store #' . $userStoreId) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
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
                                <?php if ((int) $u['id'] !== CURRENT_USER_ID): ?>
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
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="text-center p-40 text-muted">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
