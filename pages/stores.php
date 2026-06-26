<?php

declare(strict_types=1);

$userRole = $_SESSION['user']['role'] ?? '';
$isSystemAdmin = $userRole === 'admin';

// Handle approve/reject for pending stores
if ($isSystemAdmin) {
    if (isset($_GET['approve'])) {
        $sid = (int) $_GET['approve'];
        $store = getStore($db, $sid);
        if ($store && $store['status'] === 'pending') {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE stores SET status = 'active' WHERE id = ?")->execute([$sid]);
                $db->prepare("UPDATE users SET status = 'active' WHERE store_id = ? AND role = 'store_admin'")->execute([$sid]);
                $db->commit();
                logAction($db, 'approve_store', 'store', $sid, 'Approved pending store: ' . $store['name']);
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Store "' . $store['name'] . '" approved and activated.'];
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Failed to approve store.'];
            }
        }
        redirect('index.php?page=stores');
    }

    if (isset($_GET['reject'])) {
        $sid = (int) $_GET['reject'];
        $store = getStore($db, $sid);
        if ($store && $store['status'] === 'pending') {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE stores SET status = 'inactive' WHERE id = ?")->execute([$sid]);
                $db->prepare("UPDATE users SET status = 'inactive' WHERE store_id = ? AND role = 'store_admin'")->execute([$sid]);
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

    if (isset($_GET['delete'])) {
        $sid = (int) $_GET['delete'];
        $delStore = getStore($db, $sid);
        if ($sid === ACTIVE_STORE_ID) {
            $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Cannot delete the currently active store.'];
        } elseif ($delStore && $delStore['status'] === 'pending') {
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM stores WHERE id = ?")->execute([$sid]);
                $db->prepare("DELETE FROM users WHERE store_id = ? AND role = 'store_admin'")->execute([$sid]);
                $db->commit();
                logAction($db, 'delete_store', 'store', $sid, 'Deleted pending store: ' . $delStore['name']);
                $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Pending store deleted.'];
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Failed to delete store.'];
            }
        } else {
            $stmt = $db->prepare("DELETE FROM stores WHERE id = ?");
            $stmt->execute([$sid]);
        }
        redirect('index.php?page=stores');
    }

    // Switch active store
    if (isset($_GET['activate'])) {
        $sid = (int) $_GET['activate'];
        $store = getStore($db, $sid);
        if ($store && $store['status'] === 'active') {
            $_SESSION['store_id'] = $sid;
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
if ($isSystemAdmin) {
    $stmt = $db->query("SELECT COUNT(*) FROM stores WHERE status = 'pending'");
    $pendingCount = (int) $stmt->fetchColumn();
}
?>
<div class="page-header">
    <h1><i class="fas fa-store"></i> Stores
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-warning" style="font-size:14px;vertical-align:middle"><?= $pendingCount ?> pending</span>
        <?php endif; ?>
    </h1>
    <?php if ($isSystemAdmin): ?>
    <a href="index.php?page=store-form" class="btn btn-primary"><i class="fas fa-plus"></i> Add Store</a>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Currency</th>
                    <th>Tax Rate</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $s): ?>
                    <?php $isPending = ($s['status'] ?? '') === 'pending'; ?>
                    <tr style="<?= (int) $s['id'] === ACTIVE_STORE_ID ? 'background:var(--gray-100)' : ($isPending ? 'background:#fff8e1' : '') ?>">
                        <td><strong><?= e($s['name']) ?></strong>
                            <?php if ((int) $s['id'] === ACTIVE_STORE_ID): ?>
                                <span class="badge badge-primary" style="margin-left:6px">Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($s['address'] ?? '') ?></td>
                        <td><?= e($s['contact'] ?? '') ?></td>
                        <td><?= e($s['currency'] ?? 'R') ?></td>
                        <td><?= (float) ($s['tax_rate'] ?? 15) ?>%</td>
                        <td>
                            <span class="badge <?= $isPending ? 'badge-warning' : ($s['status'] === 'active' ? 'badge-success' : 'badge-danger') ?>">
                                <?= e(ucfirst($s['status'] ?? 'active')) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <?php if ($isPending && $isSystemAdmin): ?>
                                <a href="index.php?page=stores&approve=<?= (int) $s['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this store?')"><i class="fas fa-check"></i> Approve</a>
                                <a href="index.php?page=stores&reject=<?= (int) $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this store? The store and its admin account will be deactivated.')"><i class="fas fa-times"></i> Reject</a>
                            <?php elseif ($s['status'] === 'active'): ?>
                                <?php if ((int) $s['id'] !== ACTIVE_STORE_ID && $isSystemAdmin): ?>
                                    <a href="index.php?page=stores&activate=<?= (int) $s['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Activate</a>
                                <?php endif; ?>
                                <a href="index.php?page=store-form&id=<?= (int) $s['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                <?php if ((int) $s['id'] !== ACTIVE_STORE_ID && $isSystemAdmin): ?>
                                    <a href="index.php?page=stores&delete=<?= (int) $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this store? This cannot be undone.')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($stores)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center">No stores found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
