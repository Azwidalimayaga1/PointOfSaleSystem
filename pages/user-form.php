<?php

declare(strict_types=1);

$editUser = null;
$errors = [];
$success = '';
$isSystemAdmin = isSuperAdmin();
$isStoreAdmin = isStoreAdmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND store_id = ?");
        $stmt->execute([$id, currentUserStoreId()]);
    }
    $editUser = $stmt->fetch();
    if (!$editUser) redirect('index.php?page=users');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'cashier';
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    $storeId = isset($_POST['store_id']) ? (int) $_POST['store_id'] : 0;
    $userId = (int) ($_POST['user_id'] ?? 0);

    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    if (!$username) $errors[] = 'Username is required.';
    if (!$fullName) $errors[] = 'Full name is required.';
    if (!$userId && !$password) $errors[] = 'Password is required for new users.';
    if (!$userId && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required for new users.';
    if ($password && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($userId && $password) $errors[] = 'Use Firebase password reset to change an existing password.';
    if ($userId && $editUser && $email !== (string) ($editUser['email'] ?? '')) $errors[] = 'Update an existing email address in Firebase first.';

    // Check unique username
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetchColumn() > 0) $errors[] = 'Username already taken.';

    // Permission checks
    if ($role === 'super_admin' && !$isSystemAdmin) {
        $errors[] = 'You do not have permission to assign the Super Admin role.';
    }

    // Store admins cannot create other store_admins
    if ($isStoreAdmin && in_array($role, ['store_admin', 'super_admin', 'manager'], true)) {
        $errors[] = 'You do not have permission to assign this role.';
    }

    // Store admins can only create cashier users
    if ($isStoreAdmin && $role !== 'cashier') {
        $errors[] = 'As a Store Admin, you can only create Cashier users.';
    }

    // Check super_admin limit (max 3)
    if ($role === 'super_admin') {
        $limitError = checkSuperAdminLimit($db);
        // If updating existing super_admin, don't count them
        if ($editUser && $editUser['role'] === 'super_admin') {
            $limitError = null;
        }
        if ($limitError) {
            $errors[] = $limitError;
        }
    }

    // Non-super_admin users must have a store assigned
    if ($role !== 'super_admin' && $storeId <= 0) {
        $errors[] = 'A store must be selected for ' . ucfirst($role) . ' users.';
    }

    // Super admin must have store_id as NULL
    if ($role === 'super_admin') {
        $storeId = 0; // Will be set to NULL
    }

    // Store admins can only assign users to their own store
    if ($isStoreAdmin) {
        $storeId = currentUserStoreId();
    }

    if (empty($errors)) {
        $actualStoreId = $role === 'super_admin' ? null : ($storeId > 0 ? $storeId : null);

        if ($userId > 0) {
            $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, store_id=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $stmt->execute([$username, $fullName, $email ?: null, $role, $actualStoreId, $status, $userId]);
            logAction($db, 'user_update', 'user', $userId, 'Updated user: ' . $username . ' (role: ' . $role . ', status: ' . $status . ')');
            $success = 'User updated successfully.';
        } else {
            try {
                if (!$firebase instanceof FirebaseAuth) throw new RuntimeException('Firebase Authentication is not configured.');
                $firebaseUser = $firebase->createUser($email, $password);
                $firebaseUid = (string) ($firebaseUser['localId'] ?? '');
                if ($firebaseUid === '') throw new RuntimeException('Missing Firebase user ID.');
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (username, full_name, email, role, store_id, status, password, firebase_uid) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$username, $fullName, $email, $role, $actualStoreId, $status, $hash, $firebaseUid]);
                $newUserId = (int) $db->lastInsertId();
                logAction($db, 'user_create', 'user', $newUserId, 'Created user: ' . $username . ' (role: ' . $role . ')');
                $success = 'User added successfully.';
                $userId = $newUserId;
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
                $errors[] = 'User could not be created in Firebase.';
            }
        }
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $editUser = $stmt->fetch();
    }
}

$u = $editUser;

// Get stores for dropdown
$allStores = [];
if ($isSystemAdmin) {
    $allStores = $db->query("SELECT id, name FROM stores ORDER BY name ASC")->fetchAll();
}

// Super admin warning
$superAdminWarning = null;
if ($isSystemAdmin) {
    $superAdminWarning = checkSuperAdminLimit($db);
}
?>
<div class="page-header">
    <a href="index.php?page=users" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($superAdminWarning && !$u): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <?= e($superAdminWarning) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) ($u['id'] ?? 0) ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" required value="<?= e($u['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-control" required value="<?= e($u['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" value="<?= e($u['email'] ?? '') ?>" <?= $u ? 'readonly' : 'required' ?> >
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="form-control" onchange="toggleRoleFields()">
                    <?php if ($isSystemAdmin): ?>
                    <option value="super_admin" <?= ($u['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    <?php endif; ?>
                    <option value="store_admin" <?= ($u['role'] ?? '') === 'store_admin' ? 'selected' : '' ?>>Store Admin</option>
                    <option value="manager" <?= ($u['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="cashier" <?= ($u['role'] ?? '') === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= ($u['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($u['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="pending" <?= ($u['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
        </div>
        <div class="form-group" id="store-group" <?= $isSystemAdmin ? '' : 'style="display:none"' ?>>
            <label for="store_id">Assigned Store</label>
            <?php if ($isSystemAdmin): ?>
            <select id="store_id" name="store_id" class="form-control">
                <option value="">-- Select Store --</option>
                <?php foreach ($allStores as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= ($u['store_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <?php $storeName = $db->prepare("SELECT name FROM stores WHERE id = ?"); $storeName->execute([currentUserStoreId()]); ?>
            <input type="text" class="form-control" value="<?= e($storeName->fetchColumn() ?: 'Store #' . currentUserStoreId()) ?>" readonly>
            <input type="hidden" name="store_id" value="<?= (int) currentUserStoreId() ?>">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="password"><?= $u ? 'Password changes use Firebase reset' : 'Password' ?></label>
            <input type="password" id="password" name="password" class="form-control" minlength="8" <?= $u ? '' : 'required' ?>>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $u ? 'Update User' : 'Add User' ?></button>
    </form>
</div>

<script>
function toggleRoleFields() {
    var role = document.getElementById('role').value;
    var storeGroup = document.getElementById('store-group');
    if (storeGroup) {
        storeGroup.style.display = role === 'super_admin' ? 'none' : 'block';
    }
}
document.addEventListener('DOMContentLoaded', toggleRoleFields);
</script>
