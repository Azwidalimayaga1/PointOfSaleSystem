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
    $email = trim($_POST['email'] ?? '');

    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $error = 'Invalid form token. Please refresh and try again.';
    } else {
        $hpError = honeypot_validate();
        if ($hpError) {
            $error = $hpError;
        } elseif (!captcha_validate()) {
            $error = 'Incorrect CAPTCHA answer.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            $ip = getClientIp();
            $remaining = rate_limit_check_sliding($db, 'ip:' . $ip, 'forgot_password', 3, 900);
            if ($remaining <= 0) {
                $error = 'Too many password reset requests. Try again later.';
            } else {
                rate_limit_hit($db, 'ip:' . $ip, 'forgot_password');
                $token = generatePasswordResetToken($db, $email);
                logAction($db, 'password_reset_request', 'user', 0, "Password reset requested for $email from " . getClientIp());
                $success = 'If that email is registered, a reset link has been sent. Please check your email inbox.';
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
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title><?= e(STORE_NAME) ?> - Forgot Password</title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1><i class="fas fa-key"></i> <?= e(STORE_NAME) ?></h1>
        <p>Reset your password</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <div style="text-align:center;margin-top:16px">
                <a href="index.php?page=login" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Back to Login</a>
            </div>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <?= honeypot_field() ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" required autofocus>
            </div>
            <?= captcha_render() ?>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:14px;color:var(--gray-500)">
            <a href="index.php?page=login" style="color:var(--primary)">Back to Login</a>
        </p>
        <?php endif; ?>
    </div>
    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= e(STORE_NAME) ?>
    </div>
</body>
</html>
