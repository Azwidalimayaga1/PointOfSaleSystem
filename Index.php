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

if ($page === 'store-register') {
    require __DIR__ . '/pages/store-register.php';
    exit;
}

// Self-checkout standalone page (handles auth internally)
if ($page === 'self-checkout') {
    // Handle AJAX before page render
    if (isset($_POST['action']) && $_POST['action'] === 'complete_self_checkout') {
        require __DIR__ . '/pages/self-checkout-ajax.php';
        exit;
    }
    require __DIR__ . '/pages/self-checkout.php';
    exit;
}

// Handle store switch (system admin only)
if (isset($_GET['switch_store'])) {
    requireLogin();
    $role = $_SESSION['user']['role'] ?? '';
    if ($role !== 'admin') {
        redirect('index.php?page=dashboard');
    }
    $sid = (int) $_GET['switch_store'];
    $store = getStore($db, $sid);
    if ($store) {
        $_SESSION['store_id'] = $sid;
        $_SESSION['pos_flash'] = ['type' => 'success', 'message' => 'Switched to store: ' . $store['name']];
    }
    redirect('index.php?page=dashboard');
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
    logAction($db, 'logout', 'user', (int) ($_SESSION['user']['id'] ?? 0), 'User logged out: ' . ($_SESSION['user']['username'] ?? ''));
    logout();
    redirect('index.php?page=login');
}

// Page access
$rolePages = [
    'dashboard' => ['admin', 'manager', 'cashier', 'store_admin'],
    'products' => ['admin', 'manager', 'store_admin'],
    'product-form' => ['admin', 'manager', 'store_admin'],
    'sales' => ['admin', 'manager', 'cashier', 'store_admin'],
    'receipt' => ['admin', 'manager', 'cashier', 'store_admin'],
    'inventory' => ['admin', 'manager', 'store_admin'],
    'stock-adjustment' => ['admin', 'manager', 'store_admin'],
    'users' => ['admin', 'store_admin'],
    'user-form' => ['admin', 'store_admin'],
    'reports' => ['admin', 'manager', 'store_admin'],
    'settings' => ['admin'],
    'messages' => ['admin', 'manager', 'cashier', 'store_admin'],
    'admin-messages' => ['admin', 'store_admin'],
    'stores' => ['admin', 'store_admin'],
    'store-form' => ['admin', 'store_admin'],
    'customers' => ['admin', 'manager', 'store_admin'],
    'customer-form' => ['admin', 'manager', 'store_admin'],
    'customer-view' => ['admin', 'manager', 'store_admin'],
    'returns' => ['admin', 'manager', 'cashier', 'store_admin'],
    'admin-returns' => ['admin'],
    'audit-logs' => ['admin'],
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
    <script>if(localStorage.getItem('pos-theme')==='dark')document.documentElement.setAttribute('data-theme','dark')</script>
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
        <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
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
        <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
        <a href="index.php?page=self-checkout" class="<?= $page === 'self-checkout' ? 'active' : '' ?>">
            <i class="fas fa-cash-register"></i> Self Checkout
        </a>
        <?php endif; ?>
        <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
        <a href="index.php?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> Reports
        </a>
        <?php endif; ?>
        <?php if ($userRole === 'admin'): ?>
        <a href="index.php?page=stores" class="<?= $page === 'stores' || $page === 'store-form' ? 'active' : '' ?>">
            <i class="fas fa-store"></i> Stores
        </a>
        <?php endif; ?>
        <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
        <a href="index.php?page=customers" class="<?= $page === 'customers' || $page === 'customer-form' || $page === 'customer-view' ? 'active' : '' ?>">
            <i class="fas fa-user-friends"></i> Customers
        </a>
        <?php endif; ?>
        <?php if (in_array($userRole, ['admin', 'store_admin'], true)): ?>
        <a href="index.php?page=users" class="<?= $page === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <?php endif; ?>
        <?php if ($userRole === 'admin'): ?>
        <a href="index.php?page=audit-logs" class="<?= $page === 'audit-logs' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> Audit Logs
        </a>
        <a href="index.php?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <?php endif; ?>
        <?php $unreadCount = in_array($userRole, ['admin', 'manager', 'cashier', 'store_admin']) ? getUnreadMessageCount($db, (int) ($_SESSION['user']['id'] ?? 0)) : 0; ?>
        <a href="index.php?page=<?= in_array($userRole, ['admin', 'store_admin'], true) ? 'admin-messages' : 'messages' ?>" class="<?= $page === 'messages' || $page === 'admin-messages' ? 'active' : '' ?>" style="position:relative">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($unreadCount > 0): ?>
                <span style="position:absolute;top:-4px;right:-8px;background:var(--danger);color:white;font-size:11px;font-weight:700;padding:2px 7px;border-radius:50px;line-height:1.4"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="nav-user">
        <button class="btn-theme" onclick="toggleTheme()" title="Toggle theme" id="theme-toggle">
            <i class="fas fa-moon"></i>
        </button>
        <?php if ($userRole === 'admin'): ?>
        <form method="get" action="index.php" class="store-switcher">
            <select name="switch_store" onchange="this.form.submit()" class="store-switch-select" title="Switch store">
                <?php foreach ($db->query("SELECT id, name FROM stores WHERE status = 'active' ORDER BY name ASC")->fetchAll() as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === ACTIVE_STORE_ID ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <span><i class="fas fa-user"></i> <?= e($userName) ?> (<?= e($userRole === 'store_admin' ? 'Store Admin' : ucfirst($userRole)) ?>)</span>
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

function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('pos-theme', 'light');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('pos-theme', 'dark');
    }
    updateThemeIcon();
}
function updateThemeIcon() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    btn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
}
updateThemeIcon();
</script>
</body>
</html>
