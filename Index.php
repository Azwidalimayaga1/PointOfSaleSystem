<?php

declare(strict_types=1);

ob_start();

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data:; media-src 'self' blob:; worker-src 'self' blob:; connect-src 'self'");

$page = currentPage();

// Role-based default page
$role = $_SESSION['user_role'] ?? '';
if ($page === '' || $page === 'dashboard') {
    if ($role === 'cashier') {
        $page = 'sales';
    }
}

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

if ($page === 'forgot-password') {
    require __DIR__ . '/pages/forgot-password.php';
    exit;
}

if ($page === 'reset-password') {
    require __DIR__ . '/pages/reset-password.php';
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

// Handle store switch via POST only - super_admin only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_store'])) {
    requireLogin();
    if (!isSuperAdmin()) {
        logAction($db, 'access_denied_store_switch', 'user', CURRENT_USER_ID, 'Store admin attempted to switch store');
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'You do not have permission to switch stores.'];
        redirect('index.php?page=dashboard');
    }
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $_SESSION['pos_flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
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

// Handle AJAX sale submission and held sale actions
if (in_array($page, ['sales', 'pos'], true) && isset($_POST['action']) && in_array($_POST['action'], ['complete_sale', 'hold_sale', 'get_held_sale', 'delete_held_sale'], true)) {
    require __DIR__ . '/pages/sales-ajax.php';
    exit;
}

// Handle calendar AJAX
if ($page === 'calendar' && isset($_GET['action'])) {
    require __DIR__ . '/pages/calendar-ajax.php';
    exit;
}

// Handle coupon AJAX (validate coupon during checkout, toggle status, delete)
if (in_array($page, ['sales', 'pos', 'coupons'], true) && isset($_POST['action']) && in_array($_POST['action'], ['validate_coupon', 'toggle_status', 'delete'], true)) {
    require __DIR__ . '/pages/coupons-ajax.php';
    exit;
}

// Handle barcode AJAX
if ($page === 'sales' && isset($_GET['action']) && $_GET['action'] === 'barcode') {
    require __DIR__ . '/pages/sales-barcode-ajax.php';
    exit;
}

// Barcode AJAX endpoints (used from POS and product form)
if ($page === 'barcode-lookup') {
    require __DIR__ . '/pages/barcode-lookup-ajax.php';
    exit;
}
if ($page === 'barcode-generate') {
    require __DIR__ . '/pages/barcode-generate-ajax.php';
    exit;
}
if ($page === 'barcode-validate') {
    require __DIR__ . '/pages/barcode-validate-ajax.php';
    exit;
}
if ($page === 'barcode-save') {
    require __DIR__ . '/pages/barcode-save-ajax.php';
    exit;
}
if ($page === 'barcode-print') {
    require __DIR__ . '/pages/barcode-print-label.php';
    exit;
}

// Handle logout
if ($page === 'logout') {
    logAction($db, 'logout', 'user', CURRENT_USER_ID, 'User logged out: ' . ($_SESSION['user_name'] ?? ''));
    logout();
    redirect('index.php?page=login');
}

// Page access
$rolePages = [
    'dashboard' => ['super_admin', 'store_admin', 'manager', 'cashier'],
    'products' => ['super_admin', 'store_admin', 'manager'],
    'product-form' => ['super_admin', 'store_admin', 'manager'],
    'sales' => ['super_admin', 'store_admin', 'manager', 'cashier'],
    'receipt' => ['super_admin', 'store_admin', 'manager', 'cashier'],
    'inventory' => ['super_admin', 'store_admin', 'manager'],
    'stock-adjustment' => ['super_admin', 'store_admin', 'manager'],
    'users' => ['super_admin', 'store_admin'],
    'user-form' => ['super_admin', 'store_admin'],
    'reports' => ['super_admin', 'store_admin', 'manager'],
    'settings' => ['super_admin', 'store_admin'],
    'messages' => ['super_admin', 'store_admin', 'manager', 'cashier'],
    'admin-messages' => ['super_admin', 'store_admin'],
    'stores' => ['super_admin', 'store_admin'],
    'store-form' => ['super_admin', 'store_admin'],
    'customers' => ['super_admin', 'store_admin', 'manager'],
    'customer-form' => ['super_admin', 'store_admin', 'manager'],
    'customer-view' => ['super_admin', 'store_admin', 'manager'],
    'returns' => ['super_admin', 'store_admin', 'manager', 'cashier'],
    'admin-returns' => ['super_admin'],
    'audit-logs' => ['super_admin', 'store_admin'],
    'calendar' => ['super_admin', 'store_admin', 'manager', 'cashier'],
    'coupons' => ['super_admin', 'store_admin'],
    'coupon-form' => ['super_admin', 'store_admin'],
    'cashier-stats' => ['cashier'],
    'held-sales' => ['cashier'],
    'pos' => ['super_admin', 'store_admin', 'manager', 'cashier'],
];

if (!isset($rolePages[$page])) {
    $page = 'dashboard';
}

$allowedRoles = $rolePages[$page];
requireRole(...$allowedRoles);

// Map 'pos' to sales before cashier redirect check
$pageIconOrig = $page;
if ($page === 'pos') {
    $page = 'sales';
}

// Cashiers trying to access admin pages get redirected to POS
if (isCashier() && !in_array($page, ['sales', 'cashier-stats', 'receipt', 'held-sales', 'returns', 'messages', 'calendar', 'logout'], true)) {
    redirect('index.php?page=sales');
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

$userRole = userRole();
$userName = userName();

// Role display names
$roleDisplayNames = [
    'super_admin' => 'Super Admin',
    'store_admin' => 'Store Admin',
    'manager' => 'Manager',
    'cashier' => 'Cashier',
];
$roleDisplay = $roleDisplayNames[$userRole] ?? ucfirst($userRole);

// Chart pages
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
    'calendar' => 'Calendar',
    'coupons' => 'Discount Coupons',
    'coupon-form' => 'Coupon',
    'cashier-stats' => 'My Sales Stats',
    'held-sales' => 'Held Sales',
    'pos' => 'POS',
];
// Use original page for title lookup (before 'pos'→'sales' mapping)
$pageTitle = $pageTitles[$pageIconOrig] ?? ($pageTitles[$page] ?? 'Dashboard');
// For cashiers, label the POS page as "POS" not "Sales"
if (isCashier() && $page === 'sales') {
    $pageTitle = 'POS';
}

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
    'calendar' => 'calendar-alt',
    'coupons' => 'tags',
    'coupon-form' => 'tag',
    'cashier-stats' => 'chart-line',
    'held-sales' => 'pause-circle',
    'pos' => 'cash-register',
];
// Get current store name for display
$currentStoreName = '';
$stmt = $db->prepare("SELECT name FROM stores WHERE id = ?");
$stmt->execute([activeStoreId()]);
$cs = $stmt->fetch();
$currentStoreName = $cs ? $cs['name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isStoreAdmin() && $currentStoreName ? e($currentStoreName) . ' - ' : '' ?><?= e(STORE_NAME) ?> - <?= $pageTitle ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <?php if ($needsCharts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    <script src="js/app.js"></script>
    <style>
        :root { --store-accent: <?= defined('STORE_ACCENT_COLOR') ? STORE_ACCENT_COLOR : '#2563eb' ?>; }
        .sidebar-brand { color: var(--store-accent); }
        .nav-item > a.active { color: var(--store-accent); border-left-color: var(--store-accent); }
        .dropdown-menu a.active { color: var(--store-accent); }
        .topbar-title i { color: var(--store-accent); }
        .stat-icon.blue { color: var(--store-accent); background: <?= defined('STORE_ACCENT_COLOR') ? STORE_ACCENT_COLOR . '26' : 'rgba(37,99,235,0.15)' ?>; }
        .btn-primary { background: var(--store-accent); }
        .btn-primary:hover { background: var(--store-accent); opacity: 0.9; }
        .badge-info { color: var(--store-accent); }
        .page-header h1 i { color: var(--store-accent); }
        .card-header h2 i { color: var(--store-accent); }
        .tab.active { color: var(--store-accent); border-bottom-color: var(--store-accent); }
        .product-card .price { color: var(--store-accent); }
        .cashier-avatar { background: var(--store-accent); }
        .cashier-quick-action i { color: var(--store-accent); background: <?= defined('STORE_ACCENT_COLOR') ? STORE_ACCENT_COLOR . '26' : 'rgba(37,99,235,0.15)' ?>; }
        .cashier-section-title i { color: var(--store-accent); }
        .progress-fill { background: var(--store-accent); }
        .form-control:focus { border-color: var(--store-accent); box-shadow: 0 0 0 3px <?= defined('STORE_ACCENT_COLOR') ? STORE_ACCENT_COLOR . '26' : 'rgba(37,99,235,0.15)' ?>; }
        .store-switch-select:hover { border-color: var(--store-accent); }
        .store-switch-select:focus { border-color: var(--store-accent); box-shadow: 0 0 0 3px <?= defined('STORE_ACCENT_COLOR') ? STORE_ACCENT_COLOR . '26' : 'rgba(37,99,235,0.15)' ?>; }
        .store-card.active { border-color: var(--store-accent); box-shadow: 0 0 0 1px var(--store-accent); }
        .store-card:hover { border-color: var(--store-accent); }
        .held-sale-card .held-subtotal { color: var(--store-accent); }
    </style>
</head>
<body>
<script>try{(function(){var s=localStorage.getItem('pos-sidebar');if(s==='collapsed'&&window.innerWidth>768){document.body.classList.add('sidebar-collapsed')}else{document.body.classList.add('sidebar-expanded')}})()}catch(e){}</script>
<div class="app-layout">
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <?php if (!empty($storeSettings['logo_path'])): ?>
                <img src="<?= e($storeSettings['logo_path']) ?>" alt="" style="height:28px;width:auto;flex-shrink:0">
            <?php else: ?>
                <i class="fas fa-cash-register"></i>
            <?php endif; ?>
            <span class="sidebar-brand-text"><?= e(isStoreAdmin() && $currentStoreName ? $currentStoreName : (!empty($storeSettings['store_display_name']) ? $storeSettings['store_display_name'] : STORE_NAME)) ?></span>
        </div>
        <nav class="sidebar-nav">
            <?php if (isCashier()): ?>
            <!-- ===== CASHIER SIDEBAR (POS-first) ===== -->
            <div class="nav-section">Main</div>
            <div class="nav-item">
                <a href="index.php?page=sales" class="<?= $page === 'sales' ? 'active' : '' ?>">
                    <i class="fas fa-cash-register"></i> <span>POS</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="index.php?page=held-sales" class="<?= $page === 'held-sales' ? 'active' : '' ?>">
                    <i class="fas fa-pause-circle"></i> <span>Held Sales</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="index.php?page=returns" class="<?= $page === 'returns' ? 'active' : '' ?>">
                    <i class="fas fa-undo-alt"></i> <span>Returns</span>
                </a>
            </div>

            <div class="nav-section">Stats</div>
            <div class="nav-item">
                <a href="index.php?page=cashier-stats" class="<?= $page === 'cashier-stats' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> <span>My Sales Stats</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="index.php?page=receipt&id=0" class="<?= $page === 'receipt' ? 'active' : '' ?>" onclick="event.preventDefault();var id=prompt('Enter receipt ID or #:');if(id)window.location='index.php?page=receipt&id='+id;">
                    <i class="fas fa-receipt"></i> <span>Receipts</span>
                </a>
            </div>

            <div class="nav-section">Work</div>
            <div class="nav-item">
                <a href="index.php?page=calendar" class="<?= $page === 'calendar' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> <span>My Reminders</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="index.php?page=messages" class="<?= $page === 'messages' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> <span>Messages</span>
                    <?php $unreadCount = getUnreadMessageCount($db, CURRENT_USER_ID); ?>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge badge-danger ml-auto"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="nav-section">General</div>
            <div class="nav-item">
                <a href="index.php?page=logout">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>

            <?php else: ?>
            <!-- ===== ADMIN SIDEBAR (super_admin, store_admin, manager) ===== -->
            <div class="nav-section">Main</div>
            <div class="nav-item">
                <a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> <span>Dashboard</span>
                </a>
            </div>

            <!-- Inventory group -->
            <?php if (in_array($userRole, ['super_admin', 'store_admin', 'manager'], true)): ?>
            <div class="nav-section">Inventory</div>
            <div class="nav-item <?= in_array($page, ['products', 'product-form', 'inventory', 'stock-adjustment']) ? 'open' : '' ?>">
                <a href="index.php?page=products" class="dropdown-toggle">
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
            <div class="nav-item <?= in_array($page, ['sales', 'self-checkout', 'returns', 'admin-returns', 'receipt']) ? 'open' : '' ?>">
                <a href="index.php?page=sales" class="dropdown-toggle">
                    <i class="fas fa-shopping-cart"></i> <span>Sales</span> <i class="fas fa-chevron-right arrow"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="index.php?page=sales" class="<?= $page === 'sales' ? 'active' : '' ?>"><i class="fas fa-cash-register"></i> New Sale</a>
                    <?php if (in_array($userRole, ['super_admin', 'store_admin', 'manager'], true)): ?>
                    <a href="index.php?page=self-checkout" class="<?= $page === 'self-checkout' ? 'active' : '' ?>"><i class="fas fa-cart-plus"></i> Self Checkout</a>
                    <?php endif; ?>
                    <a href="index.php?page=returns" class="<?= $page === 'returns' || $page === 'admin-returns' ? 'active' : '' ?>"><i class="fas fa-undo-alt"></i> Returns</a>
                    <?php if (in_array($userRole, ['super_admin', 'store_admin'], true)): ?>
                    <a href="index.php?page=coupons" class="<?= $page === 'coupons' || $page === 'coupon-form' ? 'active' : '' ?>"><i class="fas fa-tags"></i> Discount Coupons</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Calendar / Reminders -->
            <div class="nav-section">Planning</div>
            <div class="nav-item">
                <a href="index.php?page=calendar" class="<?= $page === 'calendar' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> <span>Calendar</span>
                    <?php
                    $reminderCount = 0;
                    if (isSuperAdmin()) {
                        $rcStmt = $db->query("SELECT COUNT(*) FROM calendar_reminders WHERE status = 'pending' AND reminder_date <= CURDATE()");
                        $reminderCount = (int) $rcStmt->fetchColumn();
                    } elseif (isStoreAdmin() || isManager()) {
                        $rcStmt = $db->prepare("SELECT COUNT(*) FROM calendar_reminders WHERE status = 'pending' AND reminder_date <= CURDATE() AND store_id = ?");
                        $rcStmt->execute([activeStoreId()]);
                        $reminderCount = (int) $rcStmt->fetchColumn();
                    }
                    if ($reminderCount > 0): ?>
                        <span class="badge badge-danger ml-auto"><?= $reminderCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Stores -->
            <?php if (in_array($userRole, ['super_admin', 'store_admin'], true)): ?>
            <div class="nav-section">Organization</div>
            <div class="nav-item">
                <a href="index.php?page=stores" class="<?= $page === 'stores' || $page === 'store-form' ? 'active' : '' ?>">
                    <i class="fas fa-store"></i> <span>Stores</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Reports group -->
            <?php if (in_array($userRole, ['super_admin', 'store_admin', 'manager'], true)): ?>
            <div class="nav-item <?= in_array($page, ['reports', 'audit-logs']) ? 'open' : '' ?>">
                <a href="index.php?page=reports" class="dropdown-toggle">
                    <i class="fas fa-file-alt"></i> <span>Reports</span> <i class="fas fa-chevron-right arrow"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="index.php?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Sales Reports</a>
                    <?php if ($userRole === 'super_admin' || $userRole === 'store_admin'): ?>
                    <a href="index.php?page=audit-logs" class="<?= $page === 'audit-logs' ? 'active' : '' ?>"><i class="fas fa-history"></i> Audit Logs</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- People -->
            <?php if (in_array($userRole, ['super_admin', 'store_admin', 'manager'], true)): ?>
            <div class="nav-section">People</div>
            <div class="nav-item">
                <a href="index.php?page=customers" class="<?= $page === 'customers' || $page === 'customer-form' || $page === 'customer-view' ? 'active' : '' ?>">
                    <i class="fas fa-user-friends"></i> <span>Customers</span>
                </a>
            </div>
            <?php if (in_array($userRole, ['super_admin', 'store_admin'], true)): ?>
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
                <?php $unreadCount = getUnreadMessageCount($db, CURRENT_USER_ID); ?>
                <a href="index.php?page=<?= in_array($userRole, ['super_admin', 'store_admin'], true) ? 'admin-messages' : 'messages' ?>" class="<?= $page === 'messages' || $page === 'admin-messages' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> <span>Messages</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge badge-danger ml-auto"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Settings / System -->
            <?php if (in_array($userRole, ['super_admin', 'store_admin'], true)): ?>
            <div class="nav-section">System</div>
            <div class="nav-item">
                <a href="index.php?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> <span>Settings</span>
                </a>
            </div>
            <?php endif; ?>
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
                <span class="topbar-title"><i class="fas fa-<?= e(isCashier() && $page === 'sales' ? ($pageIcons['pos'] ?? 'cash-register') : ($pageIcons[$pageIconOrig === 'pos' ? 'pos' : $page] ?? ($pageIcons[$page] ?? 'circle'))) ?>"></i> <?= e($pageTitle) ?></span>
            </div>
            <div class="topbar-right">
                <button class="btn-theme" id="sound-toggle" title="Toggle sale notification sound">
                    <i class="fas fa-volume-up"></i>
                </button>
                <button class="btn-theme" id="theme-toggle" title="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>
                <?php if (isCashier()): ?>
                <!-- Cashier header: store name plain text, no store switcher -->
                <div class="store-badge-simple">
                    <i class="fas fa-store"></i>
                    <span><?= e($currentStoreName) ?></span>
                </div>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <span><?= e($userName) ?> <span class="role-badge"><?= e($roleDisplay) ?></span></span>
                </div>
                <a href="index.php?page=logout" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
                <?php else: ?>
                <?php if (isSuperAdmin()): ?>
                <!-- Store Switcher - super_admin only -->
                <form method="post" action="index.php" class="store-switcher">
                    <?= csrf_field() ?>
                    <input type="hidden" name="switch_store" id="switch_store_input" value="<?= ACTIVE_STORE_ID ?>">
                    <select onchange="document.getElementById('switch_store_input').value=this.value;this.form.submit()" class="store-switch-select" title="Switch store">
                        <?php foreach ($db->query("SELECT id, name, status FROM stores ORDER BY name ASC")->fetchAll() as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === ACTIVE_STORE_ID ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php elseif (isStoreAdmin() || isManager()): ?>
                <!-- Store badge - readonly for store_admin / manager -->
                <div class="store-switcher" style="display:flex;align-items:center;gap:6px;padding:4px 12px;background:var(--gray-100);border-radius:8px;font-size:13px;font-weight:600;color:var(--text)">
                    <i class="fas fa-store"></i>
                    <span><?= e($currentStoreName) ?></span>
                </div>
                <?php endif; ?>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <span><?= e($userName) ?> (<?= e($roleDisplay) ?>)</span>
                </div>
                <a href="index.php?page=logout" class="btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Flash messages -->
        <?php $flash = $_SESSION['pos_flash'] ?? null; unset($_SESSION['pos_flash']); ?>
        <?php if ($flash): ?>
        <div id="flash-message" data-flash='<?= e(json_encode(['type' => $flash['type'], 'message' => $flash['message']])) ?>'></div>
        <?php endif; ?>

        <!-- Page Content -->
        <div class="page-content">
            <?php require $pageFile; ?>
        </div>
    </div>
</div>
<!-- Sale Success Notification Modal -->
<div class="modal-overlay" id="sale-success-modal">
    <div class="modal" style="max-width:400px;text-align:center">
        <div style="font-size:48px;color:var(--success);margin-bottom:12px">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 style="margin-bottom:4px">Sale Completed Successfully</h3>
        <div id="sale-success-details" style="margin:12px 0 20px;font-size:14px;color:var(--text-secondary)"></div>
        <div class="d-flex gap-10 justify-center">
            <button class="btn btn-primary" id="btn-print-receipt" onclick="window.open(this.dataset.url,'_blank')">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button class="btn btn-success" onclick="closeSaleSuccessModal()">
                <i class="fas fa-shopping-cart"></i> New Sale
            </button>
        </div>
    </div>
</div>

<script>
// Sale success notification sound - Web Audio API (works without external file)
let saleSoundCtx = null;
const STORE_SOUND_ENABLED = <?php
    $ssStmt = $db->prepare("SELECT COALESCE(sale_sound_enabled, 1) FROM stores WHERE id = ?");
    $ssStmt->execute([activeStoreId()]);
    echo json_encode((bool) $ssStmt->fetchColumn());
?>;
const STORE_SOUND_VOLUME = <?php echo json_encode((int) ($storeSettings['sale_sound_volume'] ?? 50)); ?>;

function playSaleSuccessSound() {
    if (!STORE_SOUND_ENABLED) return;
    const soundEnabled = localStorage.getItem('saleSoundEnabled') !== 'false';
    if (!soundEnabled) return;

    // Try Web Audio API for a professional POS chime
    try {
        if (!saleSoundCtx) {
            saleSoundCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (saleSoundCtx.state === 'suspended') {
            saleSoundCtx.resume();
        }

        var now = saleSoundCtx.currentTime;
        var vol = (STORE_SOUND_VOLUME || 50) / 100 * 0.45;
        var gainNode = saleSoundCtx.createGain();
        gainNode.gain.setValueAtTime(vol, now);
        gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.8);
        gainNode.connect(saleSoundCtx.destination);

        // Pleasant two-tone chime: 880Hz + 1320Hz (A5 + E6)
        [880, 1320].forEach(function(freq, i) {
            var osc = saleSoundCtx.createOscillator();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now + i * 0.12);
            var oscGain = saleSoundCtx.createGain();
            oscGain.gain.setValueAtTime(0.35, now + i * 0.12);
            oscGain.gain.exponentialRampToValueAtTime(0.01, now + i * 0.12 + 0.5);
            osc.connect(oscGain);
            oscGain.connect(gainNode);
            osc.start(now + i * 0.12);
            osc.stop(now + i * 0.12 + 0.6);
        });
        return;
    } catch (e) {
        // Web Audio not available, fallback to file
    }

    // Fallback: try to use audio file
    try {
        var fallbackSound = new Audio('assets/sounds/sale-success.mp3');
        fallbackSound.volume = 0.45;
        fallbackSound.currentTime = 0;
        fallbackSound.play().catch(function() {});
    } catch (e) {}
}

// Sale success notification modal
function showSaleSuccessModal(data) {
    const details = document.getElementById('sale-success-details');
    let html = '<div class="fs-16 fw-bold mb-8">Receipt: ' + (data.receipt_number || '') + '</div>';
    html += '<div class="flex-between mb-4"><span>Total:</span><span class="fw-bold fs-18">' + data.formatted_total + '</span></div>';
    if (data.discount_code) {
        html += '<div class="flex-between mb-4"><span>Discount:</span><span class="text-success">' + data.discount_code + ' (-' + data.formatted_discount + ')</span></div>';
    }
    html += '<div class="flex-between"><span>Payment:</span><span>' + data.payment_method + '</span></div>';
    details.innerHTML = html;

    const btn = document.getElementById('btn-print-receipt');
    btn.dataset.url = 'index.php?page=receipt&id=' + data.sale_id;

    document.getElementById('sale-success-modal').classList.add('show');
    playSaleSuccessSound();
}

function closeSaleSuccessModal() {
    document.getElementById('sale-success-modal').classList.remove('show');
    if (window._saleSuccessRedirect) {
        window.location.href = 'index.php?page=sales';
    }
}

// Override default toast for sale success flash message
document.addEventListener('DOMContentLoaded', function() {
    // Sound toggle button
    const soundBtn = document.getElementById('sound-toggle');
    if (soundBtn) {
        function updateSoundIcon() {
            const enabled = localStorage.getItem('saleSoundEnabled') !== 'false';
            soundBtn.innerHTML = enabled ? '<i class="fas fa-volume-up"></i>' : '<i class="fas fa-volume-mute"></i>';
            soundBtn.title = enabled ? 'Mute sale notification sound' : 'Enable sale notification sound';
        }
        updateSoundIcon();
        soundBtn.addEventListener('click', function() {
            const current = localStorage.getItem('saleSoundEnabled') !== 'false';
            localStorage.setItem('saleSoundEnabled', current ? 'false' : 'true');
            updateSoundIcon();
            showToast(current ? 'Sound muted' : 'Sound enabled', 'info');
        });
    }

    // Flash message handling
    const flashEl = document.getElementById('flash-message');
    if (flashEl) {
        try {
            const flashData = JSON.parse(flashEl.dataset.flash);
            if (flashData.type === 'success' && flashData.receipt_id) {
                showSaleSuccessModal({
                    sale_id: flashData.receipt_id,
                    receipt_number: flashData.receipt_number,
                    formatted_total: flashData.formatted_total,
                    formatted_discount: flashData.formatted_discount,
                    discount_code: flashData.discount_code,
                    payment_method: flashData.payment_method
                });
            } else {
                showToast(flashData.message, flashData.type);
            }
        } catch(e) {}
    }
});
</script>

</body>
</html>
