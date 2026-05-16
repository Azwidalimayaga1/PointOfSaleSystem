<?php

declare(strict_types=1);

$editUser = null;
$errors = [];
$success = '';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
    if (!$editUser) redirect('index.php?page=users');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'cashier';
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if (!$username) $errors[] = 'Username is required.';
    if (!$fullName) $errors[] = 'Full name is required.';
    if (!$userId && !$password) $errors[] = 'Password is required for new users.';
    if ($password && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    // Check unique username
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetchColumn() > 0) $errors[] = 'Username already taken.';

    if (empty($errors)) {
        if ($userId > 0) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, role=?, status=?, password=? WHERE id=?");
                $stmt->execute([$username, $fullName, $role, $status, $hash, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, role=?, status=? WHERE id=?");
                $stmt->execute([$username, $fullName, $role, $status, $userId]);
            }
            $success = 'User updated successfully.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (username, full_name, role, status, password) VALUES (?,?,?,?,?)");
            $stmt->execute([$username, $fullName, $role, $status, $hash]);
            $success = 'User added successfully.';
            $userId = (int) $db->lastInsertId();
        }
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $editUser = $stmt->fetch();
    }
}

$u = $editUser;
?>
<div class="page-header">
    <h1><i class="fas fa-<?= $u ? 'edit' : 'plus' ?>"></i> <?= $u ? 'Edit User' : 'Add User' ?></h1>
    <a href="index.php?page=users" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px">
    <form method="post">
        <input type="hidden" name="user_id" value="<?= (int) ($u['id'] ?? 0) ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" required value="<?= e($u['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-control" required value="<?= e($u['full_name'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" class="form-control">
                    <option value="cashier" <?= ($u['role'] ?? '') === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                    <option value="manager" <?= ($u['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= ($u['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($u['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="password"><?= $u ? 'New Password (leave blank to keep current)' : 'Password' ?></label>
            <input type="password" id="password" name="password" class="form-control" minlength="6" <?= $u ? '' : 'required' ?>>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $u ? 'Update User' : 'Add User' ?></button>
    </form>
</div>
