<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['_csrf'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    $ip = getClientIp();

    // Rate limiting: 5 attempts per 15 minutes per IP (skip for localhost)
    $remaining = 5;
    if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
        $remaining = rate_limit_check_sliding($db, 'ip:' . $ip, 'login', 5, 900);
    }
    }
    if ($remaining <= 0) {
        $error = 'Too many login attempts. Please try again later.';
        logAction($db, 'failed_login_rate_limited', 'user', 0, 'Login rate limited for IP: ' . $ip . ', username: ' . $username);
    } else {
        // Check user status before attempting login
        $stmt = $db->prepare("SELECT status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $userStatus = $stmt->fetchColumn();

        if ($userStatus === 'pending') {
            $error = 'Your account is pending admin approval. Please wait.';
            logAction($db, 'failed_login_pending', 'user', 0, 'Pending account login attempt: ' . $username . ' from ' . $ip);
        } elseif ($userStatus === 'inactive') {
            $error = 'Your account has been deactivated. Contact admin.';
            logAction($db, 'failed_login_inactive', 'user', 0, 'Inactive account login attempt: ' . $username . ' from ' . $ip);
        } elseif (login($db, $username, $password, $displayName)) {
            rate_limit_hit($db, 'ip:' . $ip, 'login');
            // Role-based redirect
            $role = $_SESSION['user_role'] ?? '';
            if ($role === 'cashier') {
                redirect('index.php?page=sales');
            } else {
                redirect('index.php?page=dashboard');
            }
        } else {
            rate_limit_hit($db, 'ip:' . $ip, 'login');
            $error = 'Invalid username or password.';
            logAction($db, 'failed_login', 'user', 0, 'Failed login attempt for: ' . $username . ' from ' . $ip);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(STORE_NAME) ?> - POS Login</title>
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1><i class="fas fa-cash-register"></i> <?= e(STORE_NAME) ?></h1>
        <p>Point of Sale System</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label for="display_name">Cashier Display Name <span class="fw-normal text-muted">(optional)</span></label>
                <input type="text" name="display_name" id="display_name" class="form-control" placeholder="Your name for receipts">
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center" style="padding:12px">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        <p class="text-center mt-16 fs-14 text-muted">
            No account? <a href="index.php?page=register">Register here</a>
            &nbsp;|&nbsp;
            <a href="index.php?page=store-register">Register a new store</a>
        </p>
    </div>
</body>
</html>
