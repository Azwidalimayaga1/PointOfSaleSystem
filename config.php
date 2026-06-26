<?php

declare(strict_types=1);

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
define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// PDO connection
try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
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
            role VARCHAR(50) NOT NULL DEFAULT 'cashier',
            status VARCHAR(50) DEFAULT 'active',
            store_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            barcode VARCHAR(255) UNIQUE,
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
}

// Add missing columns to existing tables (safe for re-runs)
function addMissingColumns(PDO $db): void
{
    $cols = [
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_email VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS store_id INT DEFAULT 1",
        "ALTER TABLE sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL",
        "ALTER TABLE stock_adjustments ADD COLUMN IF NOT EXISTS store_id INT DEFAULT 1",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS store_id INT DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS store_id INT DEFAULT NULL",
    ];
    foreach ($cols as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) {}
    }
    // Migrate existing users without store_id to store 1
    try { $db->exec("UPDATE users SET store_id = 1 WHERE store_id IS NULL"); } catch (PDOException $e) {}
    // Drop CHECK constraint on role to allow 'store_admin' for existing DBs
    try { $db->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'cashier'"); } catch (PDOException $e) {}
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
    ];
    foreach ($indexes as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) {}
    }
}
addDatabaseIndexes($db);

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

$stmt = $db->prepare("SELECT COUNT(*) FROM `settings`");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $insert = $db->prepare("INSERT INTO `settings` (`key`, `value`) VALUES (?, ?)");
    foreach ($defaultSettings as $key => $value) {
        $insert->execute([$key, $value]);
    }
}

// Load settings
$settings = [];
$stmt = $db->query("SELECT `key`, `value` FROM `settings`");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

define('STORE_NAME', $settings['store_name'] ?? 'My Store');
define('STORE_ADDRESS', $settings['store_address'] ?? '');
define('STORE_CONTACT', $settings['store_contact'] ?? '');
define('TAX_RATE', (float) ($settings['tax_rate'] ?? 15));
define('CURRENCY', $settings['currency'] ?? 'R');
define('RECEIPT_FOOTER', $settings['receipt_footer'] ?? '');
define('DAILY_TARGET', (float) ($settings['daily_target'] ?? 5000));

// Default admin user
$stmt = $db->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)")
       ->execute(['admin', $hash, 'Administrator', 'admin']);
    $hash2 = password_hash('cashier123', PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)")
       ->execute(['cashier', $hash2, 'Cashier User', 'cashier']);
}

define('CURRENT_USER', $_SESSION['user'] ?? null);

// Default store
$stmt = $db->query("SELECT COUNT(*) FROM stores");
if ($stmt->fetchColumn() == 0) {
    $db->prepare("INSERT INTO stores (name, address, contact, email, currency, tax_rate, receipt_footer, daily_target, self_checkout_enabled, status) VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute([STORE_NAME, STORE_ADDRESS, STORE_CONTACT, '', CURRENCY, TAX_RATE, RECEIPT_FOOTER, DAILY_TARGET, 1, 'active']);
}

// Auto-create store_admin for each store
$stmt = $db->query("SELECT id, name FROM stores ORDER BY id");
$allStores = $stmt->fetchAll();
foreach ($allStores as $s) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'store_admin' AND store_id = ?");
    $stmt->execute([$s['id']]);
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $username = 'admin_store' . $s['id'];
        $fullName = $s['name'] . ' Admin';
        $db->prepare("INSERT INTO users (username, password, full_name, role, store_id) VALUES (?, ?, ?, 'store_admin', ?)")
           ->execute([$username, $hash, $fullName, $s['id']]);
    }
}

$activeStoreId = (int) ($_SESSION['store_id'] ?? 0);
if ($activeStoreId <= 0) {
    $stmt = $db->query("SELECT MIN(id) FROM stores");
    $activeStoreId = (int) $stmt->fetchColumn();
}
define('ACTIVE_STORE_ID', $activeStoreId ?: 1);
define('SELF_CHECKOUT_ENABLED', true);
