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

if ($token) {
    $userId = verifyEmailToken($db, $token);
    if ($userId) {
        logActivity($db, $userId, '', 'email_verified', 'Email verified from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $success = 'Email verified successfully! Please wait for admin approval.';
    } else {
        $error = 'Invalid or expired verification link.';
    }
} else {
    $error = 'Missing verification token.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title><?= e(STORE_NAME) ?> - Email Verification</title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1><i class="fas fa-envelope"></i> <?= e(STORE_NAME) ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <div style="text-align:center;margin-top:16px">
                <a href="index.php?page=login" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <div style="text-align:center;margin-top:16px">
                <a href="index.php?page=login" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= e(STORE_NAME) ?>
    </div>
</body>
</html>
