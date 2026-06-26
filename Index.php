<?php

declare(strict_types=1);

ob_start();

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'");

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
    if (isset($_POST['action']) && $_POST['action'] === 'complete_self_checkout') {
        require __DIR__ . '/pages/self-checkout-ajax.php';
        exit;
    }
    require __DIR__ . '/pages/self-checkout.php';
    exit;
}

// Handle store switch via POST only
if (isset($_GET['switch_store'])) {
    requireLogin();
    redirect('index.php?page=dashboard');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_store'])) {
    requireLogin();
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('index.php?page=dashboard');
    }
    $role = $_SESSION['user']['role'] ?? '';
    if ($role !== 'admin') {
        redirect('index.php?page=dashboard');
    }
    $sid = (int) $_POST['switch_store'];
    $store = getStore($db, $sid);
    if ($store) {
        $_SESSION['store_id'] = $sid;
        logAction($db, 'switch_store', 'store', $sid, 'Switched to store: ' . $store['name']);
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

// Chart pages: dashboard, stores, reports
$needsCharts = in_array($page, ['dashboard', 'stores', 'reports'], true);

// Page title
$pageTitles = [
    'dashboard' => 'Dashboard',
    'products' => 'Products',
    'product-form' => 'Product',
    'sales' => 'Sales',
    'receipt' => 'Receipt',
    'inventory' => 'Inventory',
    'stock-adjustment' => 'Stock Adjustment',
    'users' => 'Users',
    'user-form' => 'User',
    'reports' => 'Reports',
    'settings' => 'Settings',
    'messages' => 'Messages',
    'admin-messages' => 'Messages',
    'stores' => 'Stores',
    'store-form' => 'Store',
    'customers' => 'Customers',
    'customer-form' => 'Customer',
    'customer-view' => 'Customer',
    'returns' => 'Returns',
    'admin-returns' => 'Returns',
    'audit-logs' => 'Audit Logs',
];
$pageTitle = $pageTitles[$page] ?? 'Dashboard';

// Page icons
$pageIcons = [
    'dashboard' => 'chart-bar',
    'products' => 'box',
    'product-form' => 'box',
    'sales' => 'shopping-cart',
    'receipt' => 'receipt',
    'inventory' => 'warehouse',
    'stock-adjustment' => 'exchange-alt',
    'users' => 'users',
    'user-form' => 'user',
    'reports' => 'file-alt',
    'settings' => 'cog',
    'messages' => 'envelope',
    'admin-messages' => 'envelope',
    'stores' => 'store',
    'store-form' => 'store',
    'customers' => 'user-friends',
    'customer-form' => 'user',
    'customer-view' => 'user',
    'returns' => 'undo-alt',
    'admin-returns' => 'undo-alt',
    'audit-logs' => 'history',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(STORE_NAME) ?> - <?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <?php if ($needsCharts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    <script src="js/app.js"></script>
</head>
<body>
<div class="app-layout">
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-cash-register"></i>
            <span><?= e(STORE_NAME) ?></span>
        </div>
        <nav class="sidebar-nav">
            <!-- Main -->
            <div class="nav-section">Main</div>
            <div class="nav-item">
                <a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> <span>Dashboard</span>
                </a>
            </div>

            <!-- Inventory group -->
            <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
            <div class="nav-section">Inventory</div>
            <div class="nav-item <?= in_array($page, ['products', 'product-form', 'inventory', 'stock-adjustment']) ? 'open' : '' ?>">
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-boxes"></i> <span>Inventory</span> <i class="fas fa-chevron-right arrow"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="index.php?page=products" class="<?= in_array($page, ['products', 'product-form']) ? 'active' : '' ?>"><i class="fas fa-box"></i> Products</a>
                    <a href="index.php?page=inventory" class="<?= $page === 'inventory' ? 'active' : '' ?>"><i class="fas fa-warehouse"></i> Stock View</a>
                    <a href="index.php?page=stock-adjustment" class="<?= $page === 'stock-adjustment' ? 'active' : '' ?>"><i class="fas fa-exchange-alt"></i> Stock Adjustment</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sales group -->
            <div class="nav-section">Sales</div>
            <div class="nav-item <?= in_array($page, ['sales', 'self-checkout', 'returns', 'receipt']) ? 'open' : '' ?>">
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-shopping-cart"></i> <span>Sales</span> <i class="fas fa-chevron-right arrow"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="index.php?page=sales" class="<?= $page === 'sales' ? 'active' : '' ?>"><i class="fas fa-cash-register"></i> New Sale</a>
                    <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
                    <a href="index.php?page=self-checkout" class="<?= $page === 'self-checkout' ? 'active' : '' ?>"><i class="fas fa-cart-plus"></i> Self Checkout</a>
                    <?php endif; ?>
                    <a href="index.php?page=returns" class="<?= $page === 'returns' || $page === 'admin-returns' ? 'active' : '' ?>"><i class="fas fa-undo-alt"></i> Returns</a>
                </div>
            </div>

            <!-- Stores (admin/store_admin only) -->
            <?php if (in_array($userRole, ['admin', 'store_admin'], true)): ?>
            <div class="nav-section">Organization</div>
            <div class="nav-item">
                <a href="index.php?page=stores" class="<?= $page === 'stores' || $page === 'store-form' ? 'active' : '' ?>">
                    <i class="fas fa-store"></i> <span>Stores</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Reports group (admin/manager/store_admin) -->
            <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
            <div class="nav-item <?= in_array($page, ['reports', 'audit-logs']) ? 'open' : '' ?>">
                <a href="#" class="dropdown-toggle">
                    <i class="fas fa-file-alt"></i> <span>Reports</span> <i class="fas fa-chevron-right arrow"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="index.php?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Sales Reports</a>
                    <?php if ($userRole === 'admin'): ?>
                    <a href="index.php?page=audit-logs" class="<?= $page === 'audit-logs' ? 'active' : '' ?>"><i class="fas fa-history"></i> Audit Logs</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin group -->
            <?php if (in_array($userRole, ['admin', 'manager', 'store_admin'], true)): ?>
            <div class="nav-section">People</div>
            <div class="nav-item">
                <a href="index.php?page=customers" class="<?= $page === 'customers' || $page === 'customer-form' || $page === 'customer-view' ? 'active' : '' ?>">
                    <i class="fas fa-user-friends"></i> <span>Customers</span>
                </a>
            </div>
            <?php if (in_array($userRole, ['admin', 'store_admin'], true)): ?>
            <div class="nav-item">
                <a href="index.php?page=users" class="<?= $page === 'users' || $page === 'user-form' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> <span>Users</span>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Messages -->
            <div class="nav-section">Communication</div>
            <div class="nav-item">
                <?php $unreadCount = in_array($userRole, ['admin', 'manager', 'cashier', 'store_admin']) ? getUnreadMessageCount($db, (int) ($_SESSION['user']['id'] ?? 0)) : 0; ?>
                <a href="index.php?page=<?= in_array($userRole, ['admin', 'store_admin'], true) ? 'admin-messages' : 'messages' ?>" class="<?= $page === 'messages' || $page === 'admin-messages' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> <span>Messages</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge badge-danger ml-auto"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Settings (admin only) -->
            <?php if ($userRole === 'admin'): ?>
            <div class="nav-section">System</div>
            <div class="nav-item">
                <a href="index.php?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Collapse sidebar" title="Collapse sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="nav-toggle-btn" id="navToggle" aria-label="Toggle navigation">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="topbar-right">
                <button class="btn-theme" id="theme-toggle" title="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>
                <?php if ($userRole === 'admin'): ?>
                <form method="post" action="index.php" class="store-switcher">
                    <?= csrf_field() ?>
                    <input type="hidden" name="switch_store" id="switch_store_input" value="<?= ACTIVE_STORE_ID ?>">
                    <select onchange="document.getElementById('switch_store_input').value=this.value;this.form.submit()" class="store-switch-select" title="Switch store">
                        <?php foreach ($db->query("SELECT id, name FROM stores WHERE status = 'active' ORDER BY name ASC")->fetchAll() as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === ACTIVE_STORE_ID ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <span><?= e($userName) ?> (<?= e($userRole === 'store_admin' ? 'Store Admin' : ucfirst($userRole)) ?>)</span>
                </div>
                <a href="index.php?page=logout" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </div>
        </header>

        <!-- Flash messages -->
        <?php $flash = $_SESSION['pos_flash'] ?? null; unset($_SESSION['pos_flash']); ?>
        <?php if ($flash): ?>
        <div id="flash-message" data-flash='<?= e(json_encode(['type' => $flash['type'], 'message' => $flash['message']])) ?>'></div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <nav class="page-breadcrumb" aria-label="Breadcrumb">
            <a href="index.php?page=dashboard"><i class="fas fa-home"></i></a>
            <span class="sep">/</span>
            <span class="current"><?= e($pageTitle) ?></span>
        </nav>

        <!-- Page Content -->
        <?php require $pageFile; ?>
    </div>
</div>
</body>
</html>
