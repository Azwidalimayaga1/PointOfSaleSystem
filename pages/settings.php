<?php

declare(strict_types=1);

$errors = [];
$success = '';

// Security: determine which store we're editing
$editStoreId = activeStoreId();
if (isStoreAdmin()) {
    $editStoreId = currentUserStoreId();
}

// Super Admin can switch which store to customize
$customizeStoreId = $editStoreId;
if (isSuperAdmin() && isset($_GET['store_id'])) {
    $sid = (int) $_GET['store_id'];
    $s = getStore($db, $sid);
    if ($s) {
        $customizeStoreId = $sid;
    }
}

// Store admins can ONLY edit their own store
if (isStoreAdmin()) {
    $customizeStoreId = currentUserStoreId();
}

// Cashiers cannot access
if (isCashier()) {
    accessDenied();
}

// Load current store info
$currentStoreInfo = getStore($db, $customizeStoreId);
$storeSettings = getStoreSettings($db, $customizeStoreId);

$activeTab = $_GET['tab'] ?? 'branding';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!validate_csrf($csrf)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset' && isSuperAdmin()) {
        resetStoreSettingsToDefault($db, $customizeStoreId);
        logAction($db, 'store_settings_reset', 'store', $customizeStoreId, 'Store settings reset to default for store ID: ' . $customizeStoreId);
        $success = 'Store settings reset to WAPANDA defaults.';
        $storeSettings = getStoreSettings($db, $customizeStoreId);
    } elseif ($action === 'save') {
        $data = [];

        // Branding
        if (isset($_POST['store_display_name'])) $data['store_display_name'] = trim($_POST['store_display_name']);
        if (isset($_POST['store_slogan'])) $data['store_slogan'] = trim($_POST['store_slogan']);
        if (isset($_POST['accent_color'])) $data['accent_color'] = trim($_POST['accent_color']);
        if (isset($_POST['theme_mode'])) $data['theme_mode'] = $_POST['theme_mode'];
        if (isset($_POST['contact_number'])) $data['contact_number'] = trim($_POST['contact_number']);
        if (isset($_POST['email_address'])) $data['email_address'] = trim($_POST['email_address']);
        if (isset($_POST['physical_address'])) $data['physical_address'] = trim($_POST['physical_address']);
        if (isset($_POST['trading_hours'])) $data['trading_hours'] = trim($_POST['trading_hours']);
        if (isset($_POST['pos_background_style'])) $data['pos_background_style'] = $_POST['pos_background_style'];
        if (isset($_POST['dashboard_welcome_message'])) $data['dashboard_welcome_message'] = trim($_POST['dashboard_welcome_message']);

        // Logo upload
        if (!empty($_FILES['logo_path']['name'])) {
            $uploadResult = handleStoreLogoUpload($_FILES['logo_path'], $customizeStoreId);
            if ($uploadResult['success']) {
                $data['logo_path'] = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['message'];
            }
        }
        if (!empty($_FILES['receipt_logo_path']['name'])) {
            $uploadResult = handleStoreLogoUpload($_FILES['receipt_logo_path'], $customizeStoreId, 'receipt');
            if ($uploadResult['success']) {
                $data['receipt_logo_path'] = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['message'];
            }
        }

        // Receipt settings
        if (isset($_POST['receipt_footer'])) $data['receipt_footer'] = trim($_POST['receipt_footer']);
        if (isset($_POST['return_policy'])) $data['return_policy'] = trim($_POST['return_policy']);
        if (isset($_POST['exchange_policy'])) $data['exchange_policy'] = trim($_POST['exchange_policy']);
        if (isset($_POST['social_media_handles'])) $data['social_media_handles'] = trim($_POST['social_media_handles']);
        if (isset($_POST['whatsapp_number'])) $data['whatsapp_number'] = trim($_POST['whatsapp_number']);
        if (isset($_POST['thank_you_message'])) $data['thank_you_message'] = trim($_POST['thank_you_message']);
        $data['show_cashier_name_on_receipt'] = isset($_POST['show_cashier_name_on_receipt']) ? 1 : 0;
        $data['show_discount_on_receipt'] = isset($_POST['show_discount_on_receipt']) ? 1 : 0;
        $data['show_qr_on_receipt'] = isset($_POST['show_qr_on_receipt']) ? 1 : 0;
        $data['show_customer_details_on_receipt'] = isset($_POST['show_customer_details_on_receipt']) ? 1 : 0;

        // POS Settings
        if (isset($_POST['default_payment_method'])) $data['default_payment_method'] = $_POST['default_payment_method'];
        if (isset($_POST['allowed_payment_methods'])) $data['allowed_payment_methods'] = $_POST['allowed_payment_methods'];
        $data['enable_cash_payments'] = isset($_POST['enable_cash_payments']) ? 1 : 0;
        $data['enable_card_payments'] = isset($_POST['enable_card_payments']) ? 1 : 0;
        $data['enable_mobile_payments'] = isset($_POST['enable_mobile_payments']) ? 1 : 0;
        $data['enable_discounts'] = isset($_POST['enable_discounts']) ? 1 : 0;
        $data['enable_coupons'] = isset($_POST['enable_coupons']) ? 1 : 0;
        $data['enable_held_sales'] = isset($_POST['enable_held_sales']) ? 1 : 0;
        $data['enable_returns'] = isset($_POST['enable_returns']) ? 1 : 0;
        $data['enable_sale_sound'] = isset($_POST['enable_sale_sound']) ? 1 : 0;
        if (isset($_POST['sale_sound_volume'])) $data['sale_sound_volume'] = min(100, max(0, (int) $_POST['sale_sound_volume']));
        $data['show_product_images_on_pos'] = isset($_POST['show_product_images_on_pos']) ? 1 : 0;
        if (isset($_POST['product_grid_size'])) $data['product_grid_size'] = $_POST['product_grid_size'];
        if (isset($_POST['default_category'])) $data['default_category'] = $_POST['default_category'];
        $data['auto_focus_barcode'] = isset($_POST['auto_focus_barcode']) ? 1 : 0;

        // Sales Rules
        if (isset($_POST['daily_sales_target'])) $data['daily_sales_target'] = (float) $_POST['daily_sales_target'];
        if (isset($_POST['weekly_sales_target'])) $data['weekly_sales_target'] = (float) $_POST['weekly_sales_target'];
        if (isset($_POST['monthly_sales_target'])) $data['monthly_sales_target'] = (float) $_POST['monthly_sales_target'];
        if (isset($_POST['low_stock_threshold'])) $data['low_stock_threshold'] = (int) $_POST['low_stock_threshold'];
        if (isset($_POST['cashier_discount_limit'])) $data['cashier_discount_limit'] = (float) $_POST['cashier_discount_limit'];
        $data['require_admin_approval_high_discount'] = isset($_POST['require_admin_approval_high_discount']) ? 1 : 0;
        if (isset($_POST['max_discount_percentage'])) $data['max_discount_percentage'] = (float) $_POST['max_discount_percentage'];
        $data['allow_coupon_stacking'] = isset($_POST['allow_coupon_stacking']) ? 1 : 0;
        if (isset($_POST['discount_mode'])) $data['discount_mode'] = $_POST['discount_mode'];
        $data['enable_receipt_reprint'] = isset($_POST['enable_receipt_reprint']) ? 1 : 0;
        if (isset($_POST['return_period_days'])) $data['return_period_days'] = (int) $_POST['return_period_days'];

        // Staff Permissions
        $data['cashier_can_view_recent_sales'] = isset($_POST['cashier_can_view_recent_sales']) ? 1 : 0;
        $data['cashier_can_reprint_receipts'] = isset($_POST['cashier_can_reprint_receipts']) ? 1 : 0;
        $data['cashier_can_process_returns'] = isset($_POST['cashier_can_process_returns']) ? 1 : 0;
        $data['cashier_can_apply_discounts'] = isset($_POST['cashier_can_apply_discounts']) ? 1 : 0;
        $data['cashier_can_hold_sales'] = isset($_POST['cashier_can_hold_sales']) ? 1 : 0;
        $data['cashier_can_view_stock'] = isset($_POST['cashier_can_view_stock']) ? 1 : 0;
        $data['require_admin_approval_for_returns'] = isset($_POST['require_admin_approval_for_returns']) ? 1 : 0;
        $data['require_admin_approval_for_large_discounts'] = isset($_POST['require_admin_approval_for_large_discounts']) ? 1 : 0;

        // Dashboard Widgets
        if (isset($_POST['dashboard_widgets']) && is_array($_POST['dashboard_widgets'])) {
            $data['dashboard_widgets'] = $_POST['dashboard_widgets'];
        }

        // Reminder Categories
        if (isset($_POST['reminder_categories']) && is_array($_POST['reminder_categories'])) {
            $data['reminder_categories'] = $_POST['reminder_categories'];
        }

        // Store Policies
        if (isset($_POST['refund_policy'])) $data['refund_policy'] = trim($_POST['refund_policy']);
        if (isset($_POST['layby_policy'])) $data['layby_policy'] = trim($_POST['layby_policy']);

        // Also update the stores table for target and footer
        if (isset($_POST['daily_sales_target'])) {
            try {
                $db->prepare("UPDATE stores SET daily_target = ?, receipt_footer = ? WHERE id = ?")
                    ->execute([(float) $_POST['daily_sales_target'], trim($_POST['receipt_footer'] ?? ''), $customizeStoreId]);
            } catch (PDOException $e) {}
        }

        if (empty($errors)) {
            saveStoreSettings($db, $customizeStoreId, $data);
            logAction($db, 'store_customization_update', 'store', $customizeStoreId, 'Store customization updated for store ID: ' . $customizeStoreId);
            $success = 'Store settings saved successfully.';
            $storeSettings = getStoreSettings($db, $customizeStoreId);
        }
    }
}

// Helper for logo uploads
function handleStoreLogoUpload(array $file, int $storeId, string $prefix = 'logo'): array
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 2MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes, true)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WebP allowed.'];
    }

    $uploadDir = __DIR__ . '/../assets/store_logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!is_uploaded_file($file['tmp_name']) || !isset($extensions[$mime])) {
        return ['success' => false, 'message' => 'Invalid uploaded file.'];
    }

    // The extension is derived from inspected MIME type, never from user input.
    $ext = $extensions[$mime];
    $filename = 'store_' . $storeId . '_' . $prefix . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'Failed to save uploaded file.'];
    }

    return ['success' => true, 'path' => 'assets/store_logos/' . $filename];
}

$allStores = [];
if (isSuperAdmin()) {
    $allStores = getStores($db);
}

$categories = [];
try {
    $catStmt = $db->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND store_id = ? ORDER BY category");
    $catStmt->execute([$customizeStoreId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Current values with defaults
function sv(array $settings, string $key, $default = '') {
    return $settings[$key] ?? $default;
}

$displayName = sv($storeSettings, 'store_display_name', $currentStoreInfo['name'] ?? '');
$accentColor = sv($storeSettings, 'accent_color', '#2563eb');
$themeMode = sv($storeSettings, 'theme_mode', 'light');
$gridSize = sv($storeSettings, 'product_grid_size', 'medium');
$defPayMethod = sv($storeSettings, 'default_payment_method', 'cash');
$discountMode = sv($storeSettings, 'discount_mode', 'both');

$widgets = sv($storeSettings, 'dashboard_widgets', []);
$reminderCats = sv($storeSettings, 'reminder_categories', []);
$allowedPayMethods = sv($storeSettings, 'allowed_payment_methods', []);

if (empty($widgets)) {
    $widgets = ['today_sales', 'transactions_today', 'low_stock_items', 'best_sellers', 'recent_sales', 'sales_target', 'reminders', 'staff_performance', 'pending_returns', 'active_coupons', 'stock_alerts', 'customer_followups'];
}
if (empty($reminderCats)) {
    $reminderCats = ['stock_check', 'supplier_followup', 'staff_task', 'promotion_date', 'customer_collection', 'store_meeting', 'cleaning_task', 'end_of_day_cashup', 'monthly_report', 'product_delivery'];
}
if (empty($allowedPayMethods)) {
    $allowedPayMethods = ['cash', 'card', 'mobile'];
}

$allWidgetOptions = [
    'today_sales' => "Today's Sales",
    'transactions_today' => 'Transactions Today',
    'low_stock_items' => 'Low Stock Items',
    'best_sellers' => 'Best-Selling Products',
    'recent_sales' => 'Recent Sales',
    'sales_target' => 'Sales Target Progress',
    'reminders' => 'Store Reminders',
    'staff_performance' => 'Staff Performance',
    'pending_returns' => 'Pending Returns',
    'active_coupons' => 'Active Coupons',
    'stock_alerts' => 'Stock Alerts',
    'customer_followups' => 'Customer Follow-ups',
];

$allReminderOptions = [
    'stock_check' => 'Stock Check',
    'supplier_followup' => 'Supplier Follow-up',
    'staff_task' => 'Staff Task',
    'promotion_date' => 'Promotion Date',
    'customer_collection' => 'Customer Collection',
    'store_meeting' => 'Store Meeting',
    'cleaning_task' => 'Cleaning Task',
    'end_of_day_cashup' => 'End-of-Day Cash-up',
    'monthly_report' => 'Monthly Report',
    'product_delivery' => 'Product Delivery',
];
?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="page-header">
    <h1><i class="fas fa-paint-brush"></i> Store Customization
        <?php if ($currentStoreInfo): ?>
            <span class="fs-14 text-muted fw-normal">— <?= e($currentStoreInfo['name']) ?></span>
        <?php endif; ?>
    </h1>
    <div class="d-flex gap-8 flex-wrap">
        <?php if (isSuperAdmin() && count($allStores) > 1): ?>
            <form method="get" class="d-flex gap-6 align-center">
                <input type="hidden" name="page" value="settings">
                <label class="fs-13 fw-semibold text-muted">Store:</label>
                <select name="store_id" class="form-control" style="width:auto;min-width:180px" onchange="this.form.submit()">
                    <?php foreach ($allStores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $customizeStoreId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>
</div>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="tabs" id="settingsTabs">
        <button type="button" class="tab <?= $activeTab === 'branding' ? 'active' : '' ?>" data-tab="branding"><i class="fas fa-palette"></i> Branding</button>
        <button type="button" class="tab <?= $activeTab === 'receipt' ? 'active' : '' ?>" data-tab="receipt"><i class="fas fa-receipt"></i> Receipt</button>
        <button type="button" class="tab <?= $activeTab === 'pos' ? 'active' : '' ?>" data-tab="pos"><i class="fas fa-cash-register"></i> POS Settings</button>
        <button type="button" class="tab <?= $activeTab === 'sales_rules' ? 'active' : '' ?>" data-tab="sales_rules"><i class="fas fa-chart-line"></i> Sales Rules</button>
        <button type="button" class="tab <?= $activeTab === 'dashboard_widgets' ? 'active' : '' ?>" data-tab="dashboard_widgets"><i class="fas fa-th-large"></i> Dashboard</button>
        <button type="button" class="tab <?= $activeTab === 'reminders' ? 'active' : '' ?>" data-tab="reminders"><i class="fas fa-bell"></i> Reminders</button>
        <button type="button" class="tab <?= $activeTab === 'policies' ? 'active' : '' ?>" data-tab="policies"><i class="fas fa-file-contract"></i> Policies</button>
        <button type="button" class="tab <?= $activeTab === 'staff' ? 'active' : '' ?>" data-tab="staff"><i class="fas fa-users-cog"></i> Staff</button>
    </div>

    <!-- ===== BRANDING ===== -->
    <div class="tab-content <?= $activeTab === 'branding' ? 'active' : '' ?>" id="tab-branding">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-palette"></i> Branding & Appearance</h2></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="store_display_name">Store Display Name</label>
                    <input type="text" id="store_display_name" name="store_display_name" class="form-control" value="<?= e($displayName) ?>" placeholder="e.g. WAPANDA CLOTHING">
                </div>
                <div class="form-group">
                    <label for="store_slogan">Store Slogan</label>
                    <input type="text" id="store_slogan" name="store_slogan" class="form-control" value="<?= e(sv($storeSettings, 'store_slogan')) ?>" placeholder="e.g. Streetwear for everyday movement">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="accent_color">Accent Colour</label>
                    <div class="d-flex gap-8 align-center">
                        <input type="color" id="accent_color" name="accent_color" value="<?= e($accentColor) ?>" style="width:50px;height:40px;border:1px solid var(--border-color);border-radius:6px;cursor:pointer">
                        <input type="text" id="accent_color_hex" class="form-control" style="width:120px" value="<?= e($accentColor) ?>" maxlength="7" placeholder="#2563eb" oninput="document.getElementById('accent_color').value=this.value">
                    </div>
                    <div class="fs-12 text-muted mt-4">Choose a brand colour. System will ensure readability in both light and dark mode.</div>
                </div>
                <div class="form-group">
                    <label for="theme_mode">Default Theme Mode</label>
                    <select id="theme_mode" name="theme_mode" class="form-control">
                        <option value="light" <?= $themeMode === 'light' ? 'selected' : '' ?>>Light Mode</option>
                        <option value="dark" <?= $themeMode === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                        <option value="system" <?= $themeMode === 'system' ? 'selected' : '' ?>>Follow System</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="logo_path">Store Logo</label>
                    <input type="file" id="logo_path" name="logo_path" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if (sv($storeSettings, 'logo_path')): ?>
                        <div class="mt-8 d-flex align-center gap-8">
                            <img src="<?= e(sv($storeSettings, 'logo_path')) ?>" alt="Logo" style="max-height:50px;border-radius:6px;border:1px solid var(--border-color)">
                            <span class="fs-12 text-muted">Current logo</span>
                        </div>
                    <?php endif; ?>
                    <div class="fs-12 text-muted mt-4">Max 2MB. JPG, PNG, GIF, WebP.</div>
                </div>
                <div class="form-group">
                    <label for="receipt_logo_path">Receipt Logo</label>
                    <input type="file" id="receipt_logo_path" name="receipt_logo_path" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if (sv($storeSettings, 'receipt_logo_path')): ?>
                        <div class="mt-8 d-flex align-center gap-8">
                            <img src="<?= e(sv($storeSettings, 'receipt_logo_path')) ?>" alt="Receipt Logo" style="max-height:40px;border-radius:6px;border:1px solid var(--border-color)">
                            <span class="fs-12 text-muted">Current receipt logo</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" class="form-control" value="<?= e(sv($storeSettings, 'contact_number')) ?>" placeholder="+27 12 345 6789">
                </div>
                <div class="form-group">
                    <label for="email_address">Email Address</label>
                    <input type="email" id="email_address" name="email_address" class="form-control" value="<?= e(sv($storeSettings, 'email_address')) ?>" placeholder="store@wapanda.co.za">
                </div>
            </div>
            <div class="form-group">
                <label for="physical_address">Physical Address</label>
                <textarea id="physical_address" name="physical_address" class="form-control" rows="2"><?= e(sv($storeSettings, 'physical_address')) ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="trading_hours">Trading Hours</label>
                    <input type="text" id="trading_hours" name="trading_hours" class="form-control" value="<?= e(sv($storeSettings, 'trading_hours')) ?>" placeholder="e.g. Mon-Fri 8AM-6PM, Sat 9AM-3PM">
                </div>
                <div class="form-group">
                    <label for="pos_background_style">POS Background Style</label>
                    <select id="pos_background_style" name="pos_background_style" class="form-control">
                        <option value="default" <?= sv($storeSettings, 'pos_background_style') === 'default' ? 'selected' : '' ?>>Default</option>
                        <option value="minimal" <?= sv($storeSettings, 'pos_background_style') === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                        <option value="branded" <?= sv($storeSettings, 'pos_background_style') === 'branded' ? 'selected' : '' ?>>Branded (accent colour)</option>
                        <option value="dark" <?= sv($storeSettings, 'pos_background_style') === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="dashboard_welcome_message">Dashboard Welcome Message</label>
                <textarea id="dashboard_welcome_message" name="dashboard_welcome_message" class="form-control" rows="2"><?= e(sv($storeSettings, 'dashboard_welcome_message')) ?></textarea>
            </div>
        </div>
    </div>

    <!-- ===== RECEIPT ===== -->
    <div class="tab-content <?= $activeTab === 'receipt' ? 'active' : '' ?>" id="tab-receipt">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-receipt"></i> Receipt Customization</h2></div>
            <div class="form-group">
                <label for="receipt_footer">Receipt Footer Message</label>
                <textarea id="receipt_footer" name="receipt_footer" class="form-control" rows="2"><?= e(sv($storeSettings, 'receipt_footer', $currentStoreInfo['receipt_footer'] ?? '')) ?></textarea>
                <div class="fs-12 text-muted mt-4">Appears at the bottom of every receipt.</div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="return_policy">Return Policy</label>
                    <textarea id="return_policy" name="return_policy" class="form-control" rows="2"><?= e(sv($storeSettings, 'return_policy')) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="exchange_policy">Exchange Policy</label>
                    <textarea id="exchange_policy" name="exchange_policy" class="form-control" rows="2"><?= e(sv($storeSettings, 'exchange_policy')) ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="social_media_handles">Social Media Handles</label>
                    <input type="text" id="social_media_handles" name="social_media_handles" class="form-control" value="<?= e(sv($storeSettings, 'social_media_handles')) ?>" placeholder="Instagram/Twitter/Facebook">
                </div>
                <div class="form-group">
                    <label for="whatsapp_number">WhatsApp Number</label>
                    <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-control" value="<?= e(sv($storeSettings, 'whatsapp_number')) ?>" placeholder="+27 12 345 6789">
                </div>
            </div>
            <div class="form-group">
                <label for="thank_you_message">Thank You Message</label>
                <input type="text" id="thank_you_message" name="thank_you_message" class="form-control" value="<?= e(sv($storeSettings, 'thank_you_message')) ?>" placeholder="Thank you for shopping with us!">
            </div>
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr">
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="show_cashier_name_on_receipt" value="1" <?= (int) sv($storeSettings, 'show_cashier_name_on_receipt', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Show Cashier Name</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="show_discount_on_receipt" value="1" <?= (int) sv($storeSettings, 'show_discount_on_receipt', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Show Discount Details</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="show_qr_on_receipt" value="1" <?= (int) sv($storeSettings, 'show_qr_on_receipt') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Show QR / Barcode</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="show_customer_details_on_receipt" value="1" <?= (int) sv($storeSettings, 'show_customer_details_on_receipt') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Show Customer Details</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== POS SETTINGS ===== -->
    <div class="tab-content <?= $activeTab === 'pos' ? 'active' : '' ?>" id="tab-pos">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-cash-register"></i> POS Screen Settings</h2></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="default_payment_method">Default Payment Method</label>
                    <select id="default_payment_method" name="default_payment_method" class="form-control">
                        <option value="cash" <?= $defPayMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= $defPayMethod === 'card' ? 'selected' : '' ?>>Card</option>
                        <option value="mobile" <?= $defPayMethod === 'mobile' ? 'selected' : '' ?>>Mobile Payment</option>
                        <option value="mixed" <?= $defPayMethod === 'mixed' ? 'selected' : '' ?>>Mixed (Cash + Card)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="product_grid_size">Product Grid Size</label>
                    <select id="product_grid_size" name="product_grid_size" class="form-control">
                        <option value="small" <?= $gridSize === 'small' ? 'selected' : '' ?>>Small (more products)</option>
                        <option value="medium" <?= $gridSize === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="large" <?= $gridSize === 'large' ? 'selected' : '' ?>>Large (bigger images)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="allowed_payment_methods">Allowed Payment Methods</label>
                    <div class="d-flex flex-col gap-6 mt-4">
                        <label class="d-flex align-center gap-8" style="cursor:pointer">
                            <input type="checkbox" name="allowed_payment_methods[]" value="cash" <?= in_array('cash', $allowedPayMethods) ? 'checked' : '' ?> style="width:18px;height:18px">
                            <span>Cash</span>
                        </label>
                        <label class="d-flex align-center gap-8" style="cursor:pointer">
                            <input type="checkbox" name="allowed_payment_methods[]" value="card" <?= in_array('card', $allowedPayMethods) ? 'checked' : '' ?> style="width:18px;height:18px">
                            <span>Card</span>
                        </label>
                        <label class="d-flex align-center gap-8" style="cursor:pointer">
                            <input type="checkbox" name="allowed_payment_methods[]" value="mobile" <?= in_array('mobile', $allowedPayMethods) ? 'checked' : '' ?> style="width:18px;height:18px">
                            <span>Mobile Payment (e.g. SnapScan, Zapper)</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="default_category">Default Product Category</label>
                    <select id="default_category" name="default_category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= sv($storeSettings, 'default_category') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="fs-12 text-muted mt-4">POS will open with this category pre-selected.</div>
                </div>
            </div>
            <div class="card-header mt-16"><h3><i class="fas fa-toggle-on"></i> POS Toggles</h3></div>
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_cash_payments" value="1" <?= (int) sv($storeSettings, 'enable_cash_payments', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Cash Payments</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_card_payments" value="1" <?= (int) sv($storeSettings, 'enable_card_payments', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Card Payments</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_mobile_payments" value="1" <?= (int) sv($storeSettings, 'enable_mobile_payments', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Mobile Payments</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_discounts" value="1" <?= (int) sv($storeSettings, 'enable_discounts', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Discounts</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_coupons" value="1" <?= (int) sv($storeSettings, 'enable_coupons', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Coupons</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_held_sales" value="1" <?= (int) sv($storeSettings, 'enable_held_sales', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Held Sales</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_returns" value="1" <?= (int) sv($storeSettings, 'enable_returns', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Returns</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_sale_sound" value="1" <?= (int) sv($storeSettings, 'enable_sale_sound', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Sale Sound</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="show_product_images_on_pos" value="1" <?= (int) sv($storeSettings, 'show_product_images_on_pos', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Show Product Images</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="auto_focus_barcode" value="1" <?= (int) sv($storeSettings, 'auto_focus_barcode', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Auto-focus Barcode Input</span>
                    </label>
                </div>
            </div>
            <div class="form-group mt-8">
                <label for="sale_sound_volume">Sale Sound Volume: <span id="sound-volume-label"><?= (int) sv($storeSettings, 'sale_sound_volume', 50) ?></span>%</label>
                <input type="range" id="sale_sound_volume" name="sale_sound_volume" min="0" max="100" value="<?= (int) sv($storeSettings, 'sale_sound_volume', 50) ?>" oninput="document.getElementById('sound-volume-label').textContent=this.value" style="width:100%;max-width:300px">
            </div>
        </div>
    </div>

    <!-- ===== SALES RULES ===== -->
    <div class="tab-content <?= $activeTab === 'sales_rules' ? 'active' : '' ?>" id="tab-sales_rules">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-chart-line"></i> Sales Targets & Rules</h2></div>
            <div class="card-header"><h3>Sales Targets</h3></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="daily_sales_target">Daily Sales Target</label>
                    <input type="number" id="daily_sales_target" name="daily_sales_target" class="form-control" step="0.01" min="0" value="<?= e((string) sv($storeSettings, 'daily_sales_target', $currentStoreInfo['daily_target'] ?? '5000')) ?>">
                </div>
                <div class="form-group">
                    <label for="weekly_sales_target">Weekly Sales Target</label>
                    <input type="number" id="weekly_sales_target" name="weekly_sales_target" class="form-control" step="0.01" min="0" value="<?= e((string) sv($storeSettings, 'weekly_sales_target', '0')) ?>">
                </div>
                <div class="form-group">
                    <label for="monthly_sales_target">Monthly Sales Target</label>
                    <input type="number" id="monthly_sales_target" name="monthly_sales_target" class="form-control" step="0.01" min="0" value="<?= e((string) sv($storeSettings, 'monthly_sales_target', '0')) ?>">
                </div>
            </div>
            <div class="card-header mt-16"><h3>Stock & Discount Rules</h3></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="low_stock_threshold">Low Stock Warning Level (units)</label>
                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" min="1" value="<?= (int) sv($storeSettings, 'low_stock_threshold', 10) ?>">
                </div>
                <div class="form-group">
                    <label for="cashier_discount_limit">Cashier Discount Limit (%)</label>
                    <input type="number" id="cashier_discount_limit" name="cashier_discount_limit" class="form-control" step="0.5" min="0" max="100" value="<?= e((string) sv($storeSettings, 'cashier_discount_limit', '10')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="max_discount_percentage">Maximum Discount Allowed (%)</label>
                    <input type="number" id="max_discount_percentage" name="max_discount_percentage" class="form-control" step="0.5" min="0" max="100" value="<?= e((string) sv($storeSettings, 'max_discount_percentage', '50')) ?>">
                </div>
                <div class="form-group">
                    <label for="discount_mode">Discount Mode</label>
                    <select id="discount_mode" name="discount_mode" class="form-control">
                        <option value="both" <?= $discountMode === 'both' ? 'selected' : '' ?>>Manual Discount + Coupon Code</option>
                        <option value="coupon_only" <?= $discountMode === 'coupon_only' ? 'selected' : '' ?>>Coupon Code Only</option>
                        <option value="manual_only" <?= $discountMode === 'manual_only' ? 'selected' : '' ?>>Manual Discount Only</option>
                    </select>
                </div>
            </div>
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="require_admin_approval_high_discount" value="1" <?= (int) sv($storeSettings, 'require_admin_approval_high_discount', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Require Admin Approval for High Discounts</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="allow_coupon_stacking" value="1" <?= (int) sv($storeSettings, 'allow_coupon_stacking') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Coupon Stacking</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="enable_receipt_reprint" value="1" <?= (int) sv($storeSettings, 'enable_receipt_reprint', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Enable Receipt Reprint</span>
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="return_period_days">Return Period (days)</label>
                    <input type="number" id="return_period_days" name="return_period_days" class="form-control" min="0" max="365" value="<?= (int) sv($storeSettings, 'return_period_days', 7) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- ===== DASHBOARD WIDGETS ===== -->
    <div class="tab-content <?= $activeTab === 'dashboard_widgets' ? 'active' : '' ?>" id="tab-dashboard_widgets">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-th-large"></i> Dashboard Widgets</h2>
                <span class="fs-12 text-muted">Select which widgets appear on the store dashboard. (Super Admin dashboard remains system-wide.)</span>
            </div>
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
                <?php foreach ($allWidgetOptions as $key => $label): ?>
                    <div class="form-group">
                        <label class="d-flex align-center gap-8" style="cursor:pointer">
                            <input type="checkbox" name="dashboard_widgets[]" value="<?= e($key) ?>" <?= in_array($key, $widgets) ? 'checked' : '' ?> style="width:18px;height:18px">
                            <span><?= e($label) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== REMINDERS ===== -->
    <div class="tab-content <?= $activeTab === 'reminders' ? 'active' : '' ?>" id="tab-reminders">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-bell"></i> Reminder Categories</h2>
                <span class="fs-12 text-muted">Choose which reminder types are available for this store.</span>
            </div>
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
                <?php foreach ($allReminderOptions as $key => $label): ?>
                    <div class="form-group">
                        <label class="d-flex align-center gap-8" style="cursor:pointer">
                            <input type="checkbox" name="reminder_categories[]" value="<?= e($key) ?>" <?= in_array($key, $reminderCats) ? 'checked' : '' ?> style="width:18px;height:18px">
                            <span><?= e($label) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ===== POLICIES ===== -->
    <div class="tab-content <?= $activeTab === 'policies' ? 'active' : '' ?>" id="tab-policies">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-file-contract"></i> Store Policies</h2></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="return_policy">Return Policy</label>
                    <textarea id="return_policy" name="return_policy" class="form-control" rows="3"><?= e(sv($storeSettings, 'return_policy')) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="exchange_policy">Exchange Policy</label>
                    <textarea id="exchange_policy" name="exchange_policy" class="form-control" rows="3"><?= e(sv($storeSettings, 'exchange_policy')) ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="refund_policy">Refund Policy</label>
                    <textarea id="refund_policy" name="refund_policy" class="form-control" rows="3"><?= e(sv($storeSettings, 'refund_policy')) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="layby_policy">Lay-by Policy</label>
                    <textarea id="layby_policy" name="layby_policy" class="form-control" rows="3"><?= e(sv($storeSettings, 'layby_policy')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== STAFF PERMISSIONS ===== -->
    <div class="tab-content <?= $activeTab === 'staff' ? 'active' : '' ?>" id="tab-staff">
        <div class="card">
            <div class="card-header"><h2><i class="fas fa-users-cog"></i> Staff & Role Preferences</h2></div>
            <div class="form-row" style="grid-template-columns:1fr 1fr">
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="cashier_can_view_recent_sales" value="1" <?= (int) sv($storeSettings, 'cashier_can_view_recent_sales') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Cashiers to View Recent Sales</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="cashier_can_reprint_receipts" value="1" <?= (int) sv($storeSettings, 'cashier_can_reprint_receipts') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Cashiers to Reprint Receipts</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="cashier_can_process_returns" value="1" <?= (int) sv($storeSettings, 'cashier_can_process_returns') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Cashiers to Process Returns</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="cashier_can_apply_discounts" value="1" <?= (int) sv($storeSettings, 'cashier_can_apply_discounts') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Cashiers to Apply Discounts</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="cashier_can_hold_sales" value="1" <?= (int) sv($storeSettings, 'cashier_can_hold_sales', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Cashiers to Hold Sales</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="cashier_can_view_stock" value="1" <?= (int) sv($storeSettings, 'cashier_can_view_stock') ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Allow Cashiers to View Stock Levels</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="require_admin_approval_for_returns" value="1" <?= (int) sv($storeSettings, 'require_admin_approval_for_returns', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Require Store Admin Approval for Refunds</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="d-flex align-center gap-8" style="cursor:pointer">
                        <input type="checkbox" name="require_admin_approval_for_large_discounts" value="1" <?= (int) sv($storeSettings, 'require_admin_approval_for_large_discounts', 1) ? 'checked' : '' ?> style="width:18px;height:18px">
                        <span>Require Store Admin Approval for Large Discounts</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-10 mt-16 mb-32 flex-wrap">
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
        <?php if (isSuperAdmin()): ?>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Reset ALL store settings to WAPANDA defaults? This cannot be undone.')" formaction="?page=settings&store_id=<?= $customizeStoreId ?>" formmethod="post">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <i class="fas fa-undo"></i> Reset to Default
            </button>
        <?php endif; ?>
    </div>
</form>

<script>
(function() {
    // Tab switching
    var tabs = document.querySelectorAll('#settingsTabs .tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var tabName = this.dataset.tab;
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(function(tc) { tc.classList.remove('active'); });
            var target = document.getElementById('tab-' + tabName);
            if (target) target.classList.add('active');
            // Update URL
            var url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
        });
    });

    // Previews
    var accentInput = document.getElementById('accent_color');
    var accentHex = document.getElementById('accent_color_hex');
    if (accentInput && accentHex) {
        accentInput.addEventListener('input', function() {
            accentHex.value = this.value;
        });
        accentHex.addEventListener('input', function() {
            if (/^#[0-9a-f]{6}$/i.test(this.value)) {
                accentInput.value = this.value;
            }
        });
    }
})();
</script>
