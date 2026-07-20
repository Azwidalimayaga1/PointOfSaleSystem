<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
    $storeName = trim($_POST['store_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$storeName || !$username || !$fullName || !$password) {
        $error = 'Store name, username, full name, and password are required.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username already exists.';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM stores WHERE name = ?");
            $stmt->execute([$storeName]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'A store with this name already exists.';
            } else {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("INSERT INTO stores (name, address, contact, email, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$storeName, $address, $contact, $email]);
                    $storeId = (int) $db->lastInsertId();

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, role, status, store_id) VALUES (?, ?, ?, 'store_admin', 'pending', ?)");
                    $stmt->execute([$username, $hash, $fullName, $storeId]);

                    $db->commit();
                    $success = 'Store registration submitted! An administrator will review and activate your store. You will be notified once approved.';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Registration failed. Please try again.';
                }
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
    <title><?= e(STORE_NAME) ?> - Register Store</title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box" style="max-width:560px">
        <h1><i class="fas fa-store"></i> <?= e(STORE_NAME) ?></h1>
        <p>Register a new store</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <div class="text-center mt-16">
                <a href="index.php?page=login" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            </div>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <h3 class="section-title"><i class="fas fa-building"></i> Store Details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="store_name">Store Name</label>
                    <input type="text" name="store_name" id="store_name" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" name="address" id="address" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="contact">Contact Details</label>
                    <input type="text" name="contact" id="contact" class="form-control">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                </div>
            </div>

            <h3 class="section-title"><i class="fas fa-user-shield"></i> Admin Account</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Admin Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="full_name">Admin Full Name</label>
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
            <button type="submit" class="btn btn-success w-full justify-center" style="padding:12px">
                <i class="fas fa-paper-plane"></i> Submit Registration
            </button>
        </form>
        <p class="text-center mt-16 fs-14 text-muted">
            Already have an account? <a href="index.php?page=login">Sign in</a>
        </p>
        <?php endif; ?>
    </div>
</body>
</html>
