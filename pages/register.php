<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';
$success = '';
$stores = $db->query("SELECT * FROM stores WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $storeId = (int) ($_POST['store_id'] ?? 0);

    if (!$username || !$fullName || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Validate store exists
        $store = getStore($db, $storeId);
        if (!$store) {
            $error = 'Please select a valid store.';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, status, store_id) VALUES (?, ?, ?, 'cashier', 'pending', ?)");
                $stmt->execute([$username, $hash, $fullName, $storeId]);
                $success = 'Registration submitted! Wait for admin approval before logging in.';
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(STORE_NAME) ?> - Register</title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1><i class="fas fa-user-plus"></i> <?= e(STORE_NAME) ?></h1>
        <p>Create a new account</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <div style="text-align:center;margin-top:16px">
                <a href="index.php?page=login" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            </div>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="12">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label for="store_id">Select Store</label>
                <select name="store_id" id="store_id" class="form-control" required>
                    <option value="">-- Choose a store --</option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= isset($_POST['store_id']) && (int) $_POST['store_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-success w-full justify-center" style="padding:12px">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
        <p class="text-center mt-16 fs-14 text-muted">
            Already have an account? <a href="index.php?page=login">Sign in</a>
        </p>
        <?php endif; ?>
    </div>
</body>
</html>
