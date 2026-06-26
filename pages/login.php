<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');

    // Check user status before attempting login
    $stmt = $db->prepare("SELECT status FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $userStatus = $stmt->fetchColumn();

    if ($userStatus === 'pending') {
        $error = 'Your account is pending admin approval. Please wait.';
    } elseif ($userStatus === 'inactive') {
        $error = 'Your account has been deactivated. Contact admin.';
    } elseif (login($db, $username, $password, $displayName)) {
        logAction($db, 'login', 'user', (int) ($_SESSION['user']['id'] ?? 0), 'User logged in: ' . $username);
        redirect('index.php?page=dashboard');
    } else {
        $error = 'Invalid username or password.';
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
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="display_name">Cashier Display Name <span style="font-weight:400;color:var(--gray-400)">(optional)</span></label>
                <input type="text" name="display_name" id="display_name" class="form-control" placeholder="Your name for receipts">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:14px;color:var(--gray-500)">
            No account? <a href="index.php?page=register" style="color:var(--primary)">Register here</a>
            &nbsp;|&nbsp;
            <a href="index.php?page=store-register" style="color:var(--primary)">Register a new store</a>
        </p>
    </div>
</body>
</html>
