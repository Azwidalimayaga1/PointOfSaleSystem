<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';
$success = '';
$token = trim($_GET['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $error = 'Invalid form token. Please refresh and try again.';
    } elseif (honeypot_validate()) {
        $error = honeypot_validate();
    } elseif (!captcha_validate()) {
        $error = 'Incorrect CAPTCHA answer.';
    } elseif (!$token) {
        $error = 'Missing reset token.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $userId = verifyPasswordResetToken($db, $token);
        if (!$userId) {
            $error = 'Invalid or expired reset token. Please request a new one.';
        } else {
            resetPassword($db, $userId, $password);
            logActivity($db, $userId, '', 'password_reset', 'Password reset completed from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $success = 'Password has been reset successfully. You can now login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title><?= e(STORE_NAME) ?> - Reset Password</title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1><i class="fas fa-key"></i> <?= e(STORE_NAME) ?></h1>
        <p>Choose a new password</p>

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
            <?= honeypot_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" class="form-control" required minlength="12" autofocus>
                <small style="color:var(--gray-500)">Min 12 characters with uppercase, lowercase, number &amp; special character</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            </div>
            <?= captcha_render() ?>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                <i class="fas fa-save"></i> Reset Password
            </button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:14px;color:var(--gray-500)">
            <a href="index.php?page=forgot-password" style="color:var(--primary)">Request new reset link</a>
        </p>
        <?php endif; ?>
    </div>
    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= e(STORE_NAME) ?>
    </div>
</body>
</html>
