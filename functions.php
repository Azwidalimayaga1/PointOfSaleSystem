<?php

declare(strict_types=1);

function money(float $amount): string
{
    return CURRENCY . ' ' . number_format($amount, 2);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function currentPage(): string
{
    return $_GET['page'] ?? 'dashboard';
}

// Auth

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('index.php?page=login');
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    $user = $_SESSION['user'];
    if (!in_array($user['role'], $roles, true)) {
        redirect('index.php?page=dashboard');
    }
}

function login(PDO $db, string $username, string $password, string $displayName = ''): bool
{
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'display_name' => $displayName ?: $user['full_name'],
            'role' => $user['role'],
        ];
        return true;
    }
    return false;
}

function logout(): void
{
    unset($_SESSION['user']);
    session_destroy();
}

function hasRole(string $role): bool
{
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === $role;
}

function userRole(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

function userName(): ?string
{
    return $_SESSION['user']['display_name'] ?? $_SESSION['user']['full_name'] ?? null;
}

// Dashboard

function getTodaySales(PDO $db): array
{
    $stmt = $db->query("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()");
    return $stmt->fetch();
}

function getBestSellers(PDO $db, int $limit = 5): array
{
    $stmt = $db->prepare("
        SELECT si.product_id, si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        WHERE DATE(s.created_at) = CURDATE()
        GROUP BY si.product_id ORDER BY qty DESC LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getLowStockProducts(PDO $db, int $limit = 10): array
{
    $stmt = $db->prepare("SELECT * FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active' ORDER BY stock_quantity ASC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getRecentSales(PDO $db, int $limit = 5, string $cashierName = ''): array
{
    if ($cashierName) {
        $stmt = $db->prepare("SELECT * FROM sales WHERE cashier_name = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$cashierName, $limit]);
    } else {
        $stmt = $db->prepare("SELECT * FROM sales ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll();
}

// Products

function getProducts(PDO $db, string $search = '', string $category = '', string $stockFilter = ''): array
{
    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (name LIKE ? OR barcode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    if ($stockFilter === 'low') {
        $sql .= " AND stock_quantity <= low_stock_threshold";
    } elseif ($stockFilter === 'out') {
        $sql .= " AND stock_quantity = 0";
    }

    $sql .= " ORDER BY name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getProduct(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getCategories(PDO $db): array
{
    return $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
}

// Stock

function recordStockAdjustment(PDO $db, int $productId, int $userId, string $userName, string $type, int $qty, int $prevStock, int $newStock, string $reason = ''): void
{
    $stmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$productId, $userId, $userName, $type, $qty, $prevStock, $newStock, $reason]);
}

function getStockHistory(PDO $db, int $productId): array
{
    $stmt = $db->prepare("SELECT * FROM stock_adjustments WHERE product_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

// Reports

function salesReport(PDO $db, string $period): array
{
    $sql = "SELECT date(created_at) as day, COUNT(*) as transactions, SUM(total) as total, SUM(tax) as tax, SUM(discount) as discount FROM sales WHERE 1=1";

    if ($period === 'today') {
        $sql .= " AND date(created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY day ORDER BY day DESC";
    return $db->query($sql)->fetchAll();
}

function salesByProduct(PDO $db, string $period): array
{
    $sql = "SELECT si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total, SUM(si.cost_price * si.quantity) as cost FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE 1=1";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY si.product_name ORDER BY total DESC";
    return $db->query($sql)->fetchAll();
}

function salesByCashier(PDO $db, string $period): array
{
    $sql = "SELECT s.cashier_name, COUNT(*) as transactions, SUM(s.total) as total FROM sales s WHERE 1=1";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY s.cashier_name ORDER BY total DESC";
    return $db->query($sql)->fetchAll();
}

function profitReport(PDO $db, string $period): array
{
    $sql = "SELECT date(s.created_at) as day, SUM(si.total) as revenue, SUM(si.cost_price * si.quantity) as cost, SUM(si.total) - SUM(si.cost_price * si.quantity) as profit FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE 1=1";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY day ORDER BY day DESC";
    return $db->query($sql)->fetchAll();
}

function inventoryReport(PDO $db): array
{
    return $db->query("SELECT *, (stock_quantity * cost_price) as stock_value FROM products ORDER BY name ASC")->fetchAll();
}

function generateReceiptNumber(PDO $db): string
{
    $stmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM sales");
    $nextId = $stmt->fetchColumn();
    return 'RCP-' . date('Ymd') . '-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
}

function getSalesForExport(PDO $db, string $period): array
{
    $sql = "SELECT s.receipt_number, s.cashier_name, s.subtotal, s.tax, s.discount, s.total, s.payment_method, s.created_at FROM sales s WHERE 1=1";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " ORDER BY s.created_at DESC";
    return $db->query($sql)->fetchAll();
}

function getUnreadMessageCount(PDO $db, int $userId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id != ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getUserMessages(PDO $db, int $userId): array
{
    $stmt = $db->prepare("SELECT * FROM messages WHERE sender_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getAllUnreadMessages(PDO $db): array
{
    return $db->query("SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.is_read = 0 ORDER BY m.created_at DESC")->fetchAll();
}

function getAllMessages(PDO $db): array
{
    return $db->query("SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id ORDER BY m.created_at DESC")->fetchAll();
}

function getSalesItemsForExport(PDO $db, string $period): array
{
    $sql = "SELECT si.product_name, si.quantity, si.price, si.total, s.receipt_number, s.created_at FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE 1=1";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " ORDER BY s.created_at DESC";
    return $db->query($sql)->fetchAll();
}
