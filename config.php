<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/FirebaseAuth.php';

// Secure session cookie settings
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Database
define('DB_HOST', getenv('POS_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('POS_DB_NAME') ?: 'pos_system');
define('DB_USER', getenv('POS_DB_USER') ?: 'root');
define('DB_PASS', getenv('POS_DB_PASS') !== false ? (string) getenv('POS_DB_PASS') : '');

// Firebase Authentication is the source of truth for user credentials.
$firebase = FirebaseAuth::fromEnvironment();

// PDO connection
try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}

// Initialize tables
function initDatabase(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            firebase_uid VARCHAR(128) DEFAULT NULL UNIQUE,
            role VARCHAR(50) NOT NULL DEFAULT 'cashier',
            status VARCHAR(50) DEFAULT 'active',
            store_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            barcode VARCHAR(255) UNIQUE,
            barcode_type VARCHAR(50) DEFAULT NULL,
            barcode_image VARCHAR(255) DEFAULT NULL,
            barcode_auto_generated TINYINT(1) DEFAULT 0,
            barcode_created_at DATETIME DEFAULT NULL,
            barcode_updated_at DATETIME DEFAULT NULL,
            category VARCHAR(255),
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            cost_price DECIMAL(10,2) DEFAULT 0,
            stock_quantity INT DEFAULT 0,
            low_stock_threshold INT DEFAULT 10,
            supplier VARCHAR(255),
            image TEXT,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_number VARCHAR(255) NOT NULL UNIQUE,
            cashier_id INT NOT NULL,
            cashier_name VARCHAR(255) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            tax DECIMAL(10,2) NOT NULL DEFAULT 0,
            tax_rate DECIMAL(10,2) NOT NULL DEFAULT 15,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_type VARCHAR(50) DEFAULT 'percentage',
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(50) NOT NULL,
            cash_amount DECIMAL(10,2) DEFAULT 0,
            card_amount DECIMAL(10,2) DEFAULT 0,
            change_amount DECIMAL(10,2) DEFAULT 0,
            customer_photo_path VARCHAR(255) DEFAULT NULL,
            customer_photo_consent_at DATETIME DEFAULT NULL,
            customer_photo_delete_after DATETIME DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cashier_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            cost_price DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (sale_id) REFERENCES sales(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL CHECK(type IN ('sale','purchase','return','adjustment','damage','exchange')),
            quantity INT NOT NULL,
            previous_stock INT NOT NULL,
            new_stock INT NOT NULL,
            reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(255) PRIMARY KEY,
            `value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            sender_name VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            store_id INT DEFAULT NULL,
            is_read TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            action VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rate_limits_lookup (identifier, action, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS stores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            address TEXT,
            contact VARCHAR(255),
            email VARCHAR(255),
            currency VARCHAR(10) DEFAULT 'R',
            tax_rate DECIMAL(10,2) DEFAULT 15,
            receipt_footer TEXT,
            daily_target DECIMAL(10,2) DEFAULT 5000,
            self_checkout_enabled TINYINT DEFAULT 1,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            email VARCHAR(255),
            address TEXT,
            notes TEXT,
            store_id INT DEFAULT 1,
            visit_count INT DEFAULT 0,
            total_spent DECIMAL(10,2) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            user_role VARCHAR(50) NOT NULL,
            store_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_action (action),
            INDEX idx_audit_entity (entity_type, entity_id),
            INDEX idx_audit_store (store_id),
            INDEX idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS backups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            file_size BIGINT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'completed',
            type VARCHAR(50) DEFAULT 'manual',
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS return_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            receipt_number VARCHAR(255) NOT NULL,
            cashier_id INT NOT NULL,
            cashier_name VARCHAR(255) NOT NULL,
            items JSON NOT NULL,
            reason VARCHAR(50) NOT NULL,
            resolution VARCHAR(50) NOT NULL,
            refund_amount DECIMAL(10,2) DEFAULT 0,
            exchange_product_id INT DEFAULT NULL,
            exchange_product_name VARCHAR(255) DEFAULT NULL,
            exchange_qty INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'pending',
            admin_id INT DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            store_id INT DEFAULT 1,
            updated_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS held_sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cashier_id INT NOT NULL,
            cashier_name VARCHAR(255) NOT NULL,
            items JSON NOT NULL,
            subtotal DECIMAL(10,2) DEFAULT 0,
            discount DECIMAL(10,2) DEFAULT 0,
            tax DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) DEFAULT 0,
            store_id INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            user_id INT DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_store_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            store_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
            UNIQUE KEY uq_user_store (user_id, store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS calendar_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            reminder_date DATE NOT NULL,
            reminder_time TIME DEFAULT NULL,
            store_id INT DEFAULT NULL,
            assigned_to_user_id INT DEFAULT NULL,
            created_by_user_id INT NOT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'medium',
            reminder_type VARCHAR(50) NOT NULL DEFAULT 'other',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            is_store_wide TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_reminder_store (store_id),
            INDEX idx_reminder_assigned (assigned_to_user_id),
            INDEX idx_reminder_created_by (created_by_user_id),
            INDEX idx_reminder_date (reminder_date),
            INDEX idx_reminder_status (status),
            INDEX idx_reminder_priority (priority),
            FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS store_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL UNIQUE,
            store_display_name VARCHAR(255) DEFAULT NULL,
            store_slogan VARCHAR(255) DEFAULT NULL,
            logo_path VARCHAR(255) DEFAULT NULL,
            receipt_logo_path VARCHAR(255) DEFAULT NULL,
            accent_color VARCHAR(50) DEFAULT '#2563eb',
            theme_mode VARCHAR(20) DEFAULT 'light',
            contact_number VARCHAR(50) DEFAULT NULL,
            email_address VARCHAR(255) DEFAULT NULL,
            physical_address TEXT DEFAULT NULL,
            trading_hours VARCHAR(255) DEFAULT NULL,
            pos_background_style VARCHAR(50) DEFAULT 'default',
            dashboard_welcome_message TEXT DEFAULT NULL,
            receipt_footer TEXT DEFAULT NULL,
            return_policy TEXT DEFAULT NULL,
            exchange_policy TEXT DEFAULT NULL,
            refund_policy TEXT DEFAULT NULL,
            layby_policy TEXT DEFAULT NULL,
            social_media_handles VARCHAR(255) DEFAULT NULL,
            whatsapp_number VARCHAR(50) DEFAULT NULL,
            thank_you_message TEXT DEFAULT NULL,
            show_cashier_name_on_receipt TINYINT DEFAULT 1,
            show_discount_on_receipt TINYINT DEFAULT 1,
            show_qr_on_receipt TINYINT DEFAULT 0,
            show_customer_details_on_receipt TINYINT DEFAULT 0,
            default_payment_method VARCHAR(50) DEFAULT 'cash',
            allowed_payment_methods JSON DEFAULT NULL,
            enable_cash_payments TINYINT DEFAULT 1,
            enable_card_payments TINYINT DEFAULT 1,
            enable_mobile_payments TINYINT DEFAULT 1,
            enable_discounts TINYINT DEFAULT 1,
            enable_coupons TINYINT DEFAULT 1,
            enable_held_sales TINYINT DEFAULT 1,
            enable_returns TINYINT DEFAULT 1,
            enable_sale_sound TINYINT DEFAULT 1,
            sale_sound_volume INT DEFAULT 50,
            show_product_images_on_pos TINYINT DEFAULT 1,
            product_grid_size VARCHAR(20) DEFAULT 'medium',
            default_category VARCHAR(255) DEFAULT NULL,
            auto_focus_barcode TINYINT DEFAULT 1,
            daily_sales_target DECIMAL(10,2) DEFAULT 0,
            weekly_sales_target DECIMAL(10,2) DEFAULT 0,
            monthly_sales_target DECIMAL(10,2) DEFAULT 0,
            low_stock_threshold INT DEFAULT 10,
            cashier_discount_limit DECIMAL(5,2) DEFAULT 10,
            require_admin_approval_high_discount TINYINT DEFAULT 1,
            max_discount_percentage DECIMAL(5,2) DEFAULT 50,
            allow_coupon_stacking TINYINT DEFAULT 0,
            discount_mode VARCHAR(20) DEFAULT 'both',
            return_period_days INT DEFAULT 7,
            enable_receipt_reprint TINYINT DEFAULT 1,
            require_admin_approval_for_returns TINYINT DEFAULT 1,
            require_admin_approval_for_large_discounts TINYINT DEFAULT 1,
            dashboard_widgets JSON DEFAULT NULL,
            reminder_categories JSON DEFAULT NULL,
            staff_permissions JSON DEFAULT NULL,
            store_policies JSON DEFAULT NULL,
            cashier_can_view_recent_sales TINYINT DEFAULT 0,
            cashier_can_reprint_receipts TINYINT DEFAULT 0,
            cashier_can_process_returns TINYINT DEFAULT 0,
            cashier_can_apply_discounts TINYINT DEFAULT 0,
            cashier_can_hold_sales TINYINT DEFAULT 1,
            cashier_can_view_stock TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
            INDEX idx_store_settings_store (store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS discount_coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage' CHECK(discount_type IN ('percentage','fixed')),
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            minimum_spend DECIMAL(10,2) DEFAULT 0,
            maximum_discount DECIMAL(10,2) DEFAULT NULL,
            store_id INT DEFAULT NULL,
            applies_to VARCHAR(20) DEFAULT 'entire_sale' CHECK(applies_to IN ('entire_sale','product','category')),
            product_id INT DEFAULT NULL,
            category_id VARCHAR(255) DEFAULT NULL,
            usage_limit INT DEFAULT 0,
            used_count INT DEFAULT 0,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'active' CHECK(status IN ('active','inactive','expired')),
            created_by_user_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_coupon_code (code),
            INDEX idx_coupon_store (store_id),
            INDEX idx_coupon_status (status),
            INDEX idx_coupon_dates (start_date, end_date),
            FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS product_barcodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            store_id INT NOT NULL,
            barcode VARCHAR(100) NOT NULL,
            barcode_type VARCHAR(50) NOT NULL,
            barcode_image VARCHAR(255) DEFAULT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            UNIQUE KEY unique_store_barcode (store_id, barcode),
            INDEX idx_pb_product (product_id),
            INDEX idx_pb_store (store_id),
            INDEX idx_pb_primary (product_id, is_primary),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS barcode_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            barcode_id INT DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            old_barcode VARCHAR(100) DEFAULT NULL,
            new_barcode VARCHAR(100) DEFAULT NULL,
            user_id INT NOT NULL,
            user_name VARCHAR(255) NOT NULL,
            store_id INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bal_product (product_id),
            INDEX idx_bal_user (user_id),
            INDEX idx_bal_store (store_id),
            INDEX idx_bal_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

}

// Add missing columns to existing tables (safe for re-runs)
function addMissingColumns(PDO $db): void
{
    $cols = [
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_photo_path VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_photo_consent_at DATETIME DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_photo_delete_after DATETIME DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS store_id INT DEFAULT 1",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS discount_coupon_id INT DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS discount_code VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS discount_value DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS subtotal_before_discount DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS total_after_discount DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE stock_adjustments ADD COLUMN IF NOT EXISTS store_id INT DEFAULT 1",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS store_id INT DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS firebase_uid VARCHAR(128) DEFAULT NULL",
        "ALTER TABLE messages ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL",
        "ALTER TABLE stores ADD COLUMN IF NOT EXISTS sale_sound_enabled TINYINT DEFAULT 1",
        "ALTER TABLE held_sales ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 0",
        "ALTER TABLE held_sales ADD COLUMN IF NOT EXISTS cart_data LONGTEXT DEFAULT NULL",
        "ALTER TABLE held_sales ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode_type VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode_image VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode_auto_generated TINYINT(1) DEFAULT 0",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode_created_at DATETIME DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode_updated_at DATETIME DEFAULT NULL",
    ];
    foreach ($cols as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) {}
    }
    // Migrate existing users without store_id to store 1
    try { $db->exec("UPDATE users SET store_id = 1 WHERE store_id IS NULL"); } catch (PDOException $e) {}
    // Drop CHECK constraint on role to allow 'store_admin' for existing DBs
    try { $db->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'cashier'"); } catch (PDOException $e) {}
    // Ensure email column exists
    try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_firebase_uid (firebase_uid)"); } catch (PDOException $e) {}
    // Ensure updated_at column exists
    try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (PDOException $e) {}
    // Migrate existing 'admin' role to 'super_admin'
    try { $db->exec("UPDATE users SET role = 'super_admin' WHERE role = 'admin'"); } catch (PDOException $e) {}
    // Assign orphan non-super_admin users to first store
    try {
        $stmt = $db->query("SELECT MIN(id) FROM stores");
        $firstStoreId = (int) $stmt->fetchColumn();
        if ($firstStoreId > 0) {
            $db->prepare("UPDATE users SET store_id = ? WHERE role != 'super_admin' AND (store_id IS NULL OR store_id = 0)")->execute([$firstStoreId]);
        }
    } catch (PDOException $e) {}
}

initDatabase($db);

addMissingColumns($db);

// Add database indexes for performance
function addDatabaseIndexes(PDO $db): void
{
    $indexes = [
        "ALTER TABLE sales ADD INDEX IF NOT EXISTS idx_sales_store_id (store_id)",
        "ALTER TABLE sales ADD INDEX IF NOT EXISTS idx_sales_created_at (created_at)",
        "ALTER TABLE sale_items ADD INDEX IF NOT EXISTS idx_sale_items_sale_id (sale_id)",
        "ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_store_id (store_id)",
        "ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_barcode (barcode)",
        "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_store_id (store_id)",
        "ALTER TABLE return_requests ADD INDEX IF NOT EXISTS idx_return_requests_store_id (store_id)",
        "ALTER TABLE audit_logs ADD INDEX IF NOT EXISTS idx_audit_logs_created_at (created_at)",
        "ALTER TABLE held_sales ADD INDEX IF NOT EXISTS idx_held_user_id (user_id)",
        "ALTER TABLE held_sales ADD INDEX IF NOT EXISTS idx_held_cashier_id (cashier_id)",
        "ALTER TABLE held_sales ADD INDEX IF NOT EXISTS idx_held_store_id (store_id)",
        "ALTER TABLE product_barcodes ADD INDEX IF NOT EXISTS idx_pb_product (product_id)",
        "ALTER TABLE product_barcodes ADD INDEX IF NOT EXISTS idx_pb_store (store_id)",
    ];
    foreach ($indexes as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) {}
    }
}
addDatabaseIndexes($db);

// Determine active store from session or config
$activeStoreId = 0;
if (isset($_SESSION['store_id'])) {
    $activeStoreId = (int) $_SESSION['store_id'];
}
// API requests authenticate with bearer tokens rather than browser sessions.
// Resolve their store before ACTIVE_STORE_ID is defined so API resources cannot
// accidentally fall back to the first store in the database.
// A bearer token always wins over any incidental browser cookie that may be
// sent with the request.
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
    $tokenStoreStmt = $db->prepare("SELECT u.role, u.store_id FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.access_token = ? AND s.expires_at > NOW() AND u.status = 'active' LIMIT 1");
    $tokenStoreStmt->execute([$matches[1]]);
    $tokenUser = $tokenStoreStmt->fetch();
    if ($tokenUser) {
        $activeStoreId = (int) ($tokenUser['store_id'] ?? 0);
        if ($tokenUser['role'] === 'super_admin' && isset($_SERVER['HTTP_X_STORE_ID'])) {
            $activeStoreId = (int) $_SERVER['HTTP_X_STORE_ID'];
        }
    }
}
// For non-super_admin, always force their assigned store_id from user session
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'super_admin' && isset($_SESSION['user_store_id'])) {
    $activeStoreId = (int) $_SESSION['user_store_id'];
    $_SESSION['store_id'] = $activeStoreId;
}
if ($activeStoreId <= 0) {
    $stmt = $db->query("SELECT MIN(id) FROM stores");
    $activeStoreId = (int) $stmt->fetchColumn();
}
define('ACTIVE_STORE_ID', $activeStoreId ?: 1);

// Default settings
$defaultSettings = [
    'store_name' => 'My Store',
    'store_address' => '123 Main Street',
    'store_contact' => '+27 12 345 6789',
    'tax_rate' => '15',
    'currency' => 'R',
    'receipt_footer' => 'Thank you for your purchase!',
    'daily_target' => '5000',
];

// Load per-store settings from stores table
$settings = [];
$stmt = $db->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->execute([ACTIVE_STORE_ID]);
$store = $stmt->fetch();
if ($store) {
    $settings['store_name'] = $store['name'];
    $settings['store_address'] = $store['address'];
    $settings['store_contact'] = $store['contact'];
    $settings['tax_rate'] = (string) $store['tax_rate'];
    $settings['currency'] = $store['currency'];
    $settings['receipt_footer'] = $store['receipt_footer'];
    $settings['daily_target'] = (string) $store['daily_target'];
} else {
    // Fallback to global settings table
    $stmt = $db->query("SELECT `key`, `value` FROM `settings`");
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
}

define('STORE_NAME', $settings['store_name'] ?? 'My Store');
define('STORE_ADDRESS', $settings['store_address'] ?? '');
define('STORE_CONTACT', $settings['store_contact'] ?? '');
define('TAX_RATE', (float) ($settings['tax_rate'] ?? 15));
define('CURRENCY', $settings['currency'] ?? 'R');
define('RECEIPT_FOOTER', $settings['receipt_footer'] ?? '');
define('DAILY_TARGET', (float) ($settings['daily_target'] ?? 5000));
// Customer photos are collected only with explicit consent and are automatically
// deleted after this period. Change only alongside the published privacy notice.
define('CUSTOMER_PHOTO_RETENTION_DAYS', 30);

// Load per-store customization from store_settings table
$storeSettings = [];
try {
    $ssStmt = $db->prepare("SELECT * FROM store_settings WHERE store_id = ?");
    $ssStmt->execute([ACTIVE_STORE_ID]);
    $ss = $ssStmt->fetch();
    if ($ss) {
        $storeSettings = $ss;
        // Decode JSON fields
        foreach (['allowed_payment_methods', 'dashboard_widgets', 'reminder_categories', 'staff_permissions', 'store_policies'] as $jsonField) {
            if (isset($storeSettings[$jsonField]) && is_string($storeSettings[$jsonField])) {
                $storeSettings[$jsonField] = json_decode($storeSettings[$jsonField], true) ?: [];
            }
        }
    }
} catch (PDOException $e) {
    $storeSettings = [];
}

define('STORE_ACCENT_COLOR', $storeSettings['accent_color'] ?? '#2563eb');

// Never create predictable default accounts. Provision the first administrator
// through a one-time deployment/CLI process with a unique, strong password.

define('CURRENT_USER', $_SESSION['user'] ?? null);
define('CURRENT_USER_ID', (int) ($_SESSION['user_id'] ?? 0));
define('CURRENT_USER_ROLE', $_SESSION['user_role'] ?? '');
define('CURRENT_USER_STORE_ID', isset($_SESSION['user_store_id']) ? (int) $_SESSION['user_store_id'] : null);
define('IS_SUPER_ADMIN', !empty($_SESSION['is_super_admin']));

// Default store
$stmt = $db->query("SELECT COUNT(*) FROM stores");
if ($stmt->fetchColumn() == 0) {
    $db->prepare("INSERT INTO stores (name, address, contact, email, currency, tax_rate, receipt_footer, daily_target, self_checkout_enabled, status) VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([STORE_NAME, STORE_ADDRESS, STORE_CONTACT, '', CURRENCY, TAX_RATE, RECEIPT_FOOTER, DAILY_TARGET, 1, 'active']);
}

define('SELF_CHECKOUT_ENABLED', true);
