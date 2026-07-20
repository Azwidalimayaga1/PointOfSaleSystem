<?php
declare(strict_types=1);

// This destructive migration is intentionally command-line only. It must never
// be reachable through the web server, even if server configuration is wrong.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo "=== SQLite to MySQL/MariaDB Migration Tool ===\n\n";

// Step 1: Connect to SQLite
echo "[1/5] Connecting to SQLite...\n";
try {
    $sqlite = new PDO('sqlite:' . __DIR__ . '/pos.db');
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "  SQLite connected successfully.\n";
} catch (PDOException $e) {
    die("  ERROR: " . $e->getMessage() . "\n");
}

// Step 2: Create MySQL database and tables
echo "[2/5] Setting up MySQL/MariaDB...\n";
$mysqlHost = 'localhost';
$mysqlUser = 'root';
$mysqlPass = '';
$mysqlDb = 'pos_system';

try {
    // Connect without database to create it
    $mysql = new PDO("mysql:host=$mysqlHost;charset=utf8mb4", $mysqlUser, $mysqlPass);
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $mysql->exec("CREATE DATABASE IF NOT EXISTS `$mysqlDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql->exec("USE `$mysqlDb`");

    // Drop existing tables to start fresh
    $mysql->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['sale_items', 'stock_adjustments', 'sales', 'messages', 'settings', 'products', 'users'];
    foreach ($tables as $t) {
        $mysql->exec("DROP TABLE IF EXISTS `$t`");
    }
    $mysql->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "  MySQL database '$mysqlDb' ready.\n";
} catch (PDOException $e) {
    die("  ERROR: " . $e->getMessage() . "\n");
}

// Step 3: Create tables
echo "[3/5] Creating tables...\n";

try {
    $mysql->exec("
        CREATE TABLE `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(255) NOT NULL,
            `role` VARCHAR(50) NOT NULL CHECK (`role` IN ('admin','manager','cashier')),
            `status` VARCHAR(50) DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysql->exec("
        CREATE TABLE `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `barcode` VARCHAR(255) UNIQUE,
            `category` VARCHAR(255),
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `cost_price` DECIMAL(10,2) DEFAULT 0,
            `stock_quantity` INT DEFAULT 0,
            `low_stock_threshold` INT DEFAULT 10,
            `supplier` VARCHAR(255),
            `image` TEXT,
            `status` VARCHAR(50) DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysql->exec("
        CREATE TABLE `sales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `receipt_number` VARCHAR(255) NOT NULL UNIQUE,
            `cashier_id` INT NOT NULL,
            `cashier_name` VARCHAR(255) NOT NULL,
            `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `tax` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `tax_rate` DECIMAL(10,2) NOT NULL DEFAULT 15,
            `discount` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `discount_type` VARCHAR(50) DEFAULT 'percentage',
            `total` DECIMAL(10,2) NOT NULL DEFAULT 0,
            `payment_method` VARCHAR(50) NOT NULL,
            `cash_amount` DECIMAL(10,2) DEFAULT 0,
            `card_amount` DECIMAL(10,2) DEFAULT 0,
            `change_amount` DECIMAL(10,2) DEFAULT 0,
            `status` VARCHAR(50) DEFAULT 'completed',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysql->exec("
        CREATE TABLE `sale_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sale_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `quantity` INT NOT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT 0,
            `total` DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`),
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysql->exec("
        CREATE TABLE `stock_adjustments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `user_name` VARCHAR(255) NOT NULL,
            `type` VARCHAR(50) NOT NULL CHECK (`type` IN ('sale','purchase','return','adjustment','damage')),
            `quantity` INT NOT NULL,
            `previous_stock` INT NOT NULL,
            `new_stock` INT NOT NULL,
            `reason` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysql->exec("
        CREATE TABLE `settings` (
            `key_name` VARCHAR(255) PRIMARY KEY,
            `value` TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysql->exec("
        CREATE TABLE `messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sender_id` INT NOT NULL,
            `sender_name` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "  All tables created successfully.\n";
} catch (PDOException $e) {
    die("  ERROR creating tables: " . $e->getMessage() . "\n");
}

// Step 4: Transfer data
echo "[4/5] Transferring data...\n";

try {
    // Users
    $rows = $sqlite->query("SELECT * FROM users")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['id'], $r['username'], $r['password'], $r['full_name'], $r['role'], $r['status'] ?? 'active', $r['created_at']]);
        }
    }
    echo "  Users: " . count($rows) . " rows\n";

    // Products
    $rows = $sqlite->query("SELECT * FROM products")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `products` (`id`, `name`, `barcode`, `category`, `price`, `cost_price`, `stock_quantity`, `low_stock_threshold`, `supplier`, `image`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['id'], $r['name'], $r['barcode'], $r['category'], $r['price'], $r['cost_price'], $r['stock_quantity'], $r['low_stock_threshold'], $r['supplier'] ?? '', $r['image'] ?? '', $r['status'] ?? 'active', $r['created_at'], $r['updated_at'] ?? $r['created_at']]);
        }
    }
    echo "  Products: " . count($rows) . " rows\n";

    // Sales
    $rows = $sqlite->query("SELECT * FROM sales")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `sales` (`id`, `receipt_number`, `cashier_id`, `cashier_name`, `subtotal`, `tax`, `tax_rate`, `discount`, `discount_type`, `total`, `payment_method`, `cash_amount`, `card_amount`, `change_amount`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['id'], $r['receipt_number'], $r['cashier_id'], $r['cashier_name'], $r['subtotal'], $r['tax'], $r['tax_rate'], $r['discount'], $r['discount_type'] ?? 'percentage', $r['total'], $r['payment_method'], $r['cash_amount'] ?? 0, $r['card_amount'] ?? 0, $r['change_amount'] ?? 0, $r['status'] ?? 'completed', $r['created_at']]);
        }
    }
    echo "  Sales: " . count($rows) . " rows\n";

    // Sale Items
    $rows = $sqlite->query("SELECT * FROM sale_items")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `quantity`, `price`, `cost_price`, `total`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['id'], $r['sale_id'], $r['product_id'], $r['product_name'], $r['quantity'], $r['price'], $r['cost_price'] ?? 0, $r['total']]);
        }
    }
    echo "  Sale Items: " . count($rows) . " rows\n";

    // Stock Adjustments
    $rows = $sqlite->query("SELECT * FROM stock_adjustments")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `stock_adjustments` (`id`, `product_id`, `user_id`, `user_name`, `type`, `quantity`, `previous_stock`, `new_stock`, `reason`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['id'], $r['product_id'], $r['user_id'], $r['user_name'], $r['type'], $r['quantity'], $r['previous_stock'], $r['new_stock'], $r['reason'] ?? '', $r['created_at']]);
        }
    }
    echo "  Stock Adjustments: " . count($rows) . " rows\n";

    // Settings
    $rows = $sqlite->query("SELECT * FROM settings")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `settings` (`key_name`, `value`) VALUES (?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['key'], $r['value']]);
        }
    }
    echo "  Settings: " . count($rows) . " rows\n";

    // Messages
    $rows = $sqlite->query("SELECT * FROM messages")->fetchAll();
    if ($rows) {
        $stmt = $mysql->prepare("INSERT INTO `messages` (`id`, `sender_id`, `sender_name`, `message`, `is_read`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$r['id'], $r['sender_id'], $r['sender_name'], $r['message'], $r['is_read'] ?? 0, $r['created_at']]);
        }
    }
    echo "  Messages: " . count($rows) . " rows\n";

    echo "  Transfer completed successfully!\n";
} catch (PDOException $e) {
    die("  ERROR transferring data: " . $e->getMessage() . "\n");
}

// Step 5: Update auto_increment values
echo "[5/5] Resetting auto_increment sequences...\n";
try {
    foreach ($tables as $t) {
        $maxId = $sqlite->query("SELECT COALESCE(MAX(id), 0) FROM `$t`")->fetchColumn();
        $nextId = (int) $maxId + 1;
        $mysql->exec("ALTER TABLE `$t` AUTO_INCREMENT = $nextId");
    }
    echo "  Auto-increment sequences updated.\n";
} catch (PDOException $e) {
    echo "  WARNING: Could not reset auto_increment: " . $e->getMessage() . "\n";
}

echo "\n=== Migration Complete! ===\n";
echo "Database '$mysqlDb' is ready at $mysqlHost.\n";
echo "Next: config.php needs to be updated to connect to MySQL.\n";
