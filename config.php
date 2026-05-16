<?php

declare(strict_types=1);

session_start();

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// PDO connection
try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
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
            role VARCHAR(50) NOT NULL CHECK(role IN ('admin','manager','cashier')),
            status VARCHAR(50) DEFAULT 'active',
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
            type VARCHAR(50) NOT NULL CHECK(type IN ('sale','purchase','return','adjustment','damage')),
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
}

initDatabase($db);

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
