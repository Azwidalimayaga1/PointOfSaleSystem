<?php

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$page = currentPage();

// Public pages
if ($page === 'login') {
    require __DIR__ . '/pages/login.php';
    exit;
}

if ($page === 'register') {
    require __DIR__ . '/pages/register.php';
    exit;
}

// Protected pages
requireLogin();

// Handle AJAX sale submission before any output
if ($page === 'sales' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    require __DIR__ . '/pages/sales-ajax.php';
    exit;
}

// Handle logout
if ($page === 'logout') {
    logout();
    redirect('index.php?page=login');
}

// Page access
$rolePages = [
    'dashboard' => ['admin', 'manager', 'cashier'],
    'products' => ['admin', 'manager'],
    'product-form' => ['admin', 'manager'],
    'sales' => ['admin', 'manager', 'cashier'],
    'receipt' => ['admin', 'manager', 'cashier'],
    'inventory' => ['admin', 'manager'],
    'stock-adjustment' => ['admin', 'manager'],
    'users' => ['admin'],
    'user-form' => ['admin'],
    'reports' => ['admin', 'manager'],
    'settings' => ['admin'],
    'messages' => ['admin', 'manager', 'cashier'],
    'admin-messages' => ['admin'],
];

if (!isset($rolePages[$page])) {
    $page = 'dashboard';
}

$allowedRoles = $rolePages[$page];
requireRole(...$allowedRoles);

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

$userName = userName();
$userRole = userRole();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(STORE_NAME) ?> - POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<nav class="navbar">
    <div class="nav-brand">
        <i class="fas fa-cash-register"></i>
        <span><?= e(STORE_NAME) ?></span>
    </div>
    <div class="nav-links">
        <a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Dashboard
        </a>
        <?php if (in_array($userRole, ['admin', 'manager'], true)): ?>
        <a href="index.php?page=products" class="<?= $page === 'products' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="index.php?page=inventory" class="<?= $page === 'inventory' ? 'active' : '' ?>">
            <i class="fas fa-warehouse"></i> Inventory
        </a>
        <?php endif; ?>
        <a href="index.php?page=sales" class="<?= $page === 'sales' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i> Sales
        </a>
        <?php if (in_array($userRole, ['admin', 'manager'], true)): ?>
        <a href="index.php?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> Reports
        </a>
        <?php endif; ?>
        <?php if ($userRole === 'admin'): ?>
        <a href="index.php?page=users" class="<?= $page === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <a href="index.php?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <?php endif; ?>
        <?php $unreadCount = in_array($userRole, ['admin', 'manager', 'cashier']) ? getUnreadMessageCount($db, (int) ($_SESSION['user']['id'] ?? 0)) : 0; ?>
        <a href="index.php?page=<?= $userRole === 'admin' ? 'admin-messages' : 'messages' ?>" class="<?= $page === 'messages' || $page === 'admin-messages' ? 'active' : '' ?>" style="position:relative">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($unreadCount > 0): ?>
                <span style="position:absolute;top:-4px;right:-8px;background:var(--danger);color:white;font-size:11px;font-weight:700;padding:2px 7px;border-radius:50px;line-height:1.4"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="nav-user">
        <span><i class="fas fa-user"></i> <?= e($userName) ?> (<?= ucfirst(e($userRole)) ?>)</span>
        <a href="index.php?page=logout" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <button class="nav-toggle" onclick="document.querySelector('.nav-links').classList.toggle('show')">
        <i class="fas fa-bars"></i>
    </button>
</nav>

<main class="main-content">
    <?php require $pageFile; ?>
</main>

<script>
document.querySelectorAll('.table-container table').forEach(t => {
    const wrapper = document.createElement('div');
    wrapper.className = 'table-responsive';
    t.parentNode.insertBefore(wrapper, t);
    wrapper.appendChild(t);
});
</script>
</body>
</html>
