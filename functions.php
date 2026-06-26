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
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
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
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'display_name' => $displayName ?: $user['full_name'],
            'role' => $user['role'],
            'store_id' => $user['store_id'] ? (int) $user['store_id'] : null,
        ];
        // Assign user to their store on login
        if (!empty($user['store_id'])) {
            $_SESSION['store_id'] = (int) $user['store_id'];
        }
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
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ?");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetch();
}

function getBestSellers(PDO $db, int $limit = 5): array
{
    $stmt = $db->prepare("
        SELECT si.product_id, si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        WHERE DATE(s.created_at) = CURDATE() AND s.store_id = ?
        GROUP BY si.product_id ORDER BY qty DESC LIMIT ?
    ");
    $stmt->execute([activeStoreId(), $limit]);
    return $stmt->fetchAll();
}

function getLowStockProducts(PDO $db, int $limit = 10): array
{
    $stmt = $db->prepare("SELECT * FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active' AND store_id = ? ORDER BY stock_quantity ASC LIMIT ?");
    $stmt->execute([activeStoreId(), $limit]);
    return $stmt->fetchAll();
}

function getAllLowStockProducts(PDO $db, int $limit = 20): array
{
    $stmt = $db->prepare("SELECT p.*, s.name as store_name FROM products p JOIN stores s ON s.id = p.store_id WHERE p.stock_quantity <= p.low_stock_threshold AND p.status = 'active' ORDER BY p.stock_quantity ASC, s.name ASC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getRecentSales(PDO $db, int $limit = 5, string $cashierName = ''): array
{
    if ($cashierName) {
        $stmt = $db->prepare("SELECT * FROM sales WHERE cashier_name = ? AND store_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$cashierName, activeStoreId(), $limit]);
    } else {
        $stmt = $db->prepare("SELECT * FROM sales WHERE store_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([activeStoreId(), $limit]);
    }
    return $stmt->fetchAll();
}

function getRecentSalesAllStores(PDO $db, int $limit = 10): array
{
    $stmt = $db->query("SELECT s.*, st.name as store_name FROM sales s JOIN stores st ON st.id = s.store_id ORDER BY s.created_at DESC LIMIT $limit");
    return $stmt->fetchAll();
}

// Products

function getProducts(PDO $db, string $search = '', string $category = '', string $stockFilter = ''): array
{
    $sql = "SELECT * FROM products WHERE store_id = ?";
    $params = [activeStoreId()];

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
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
    $stmt->execute([$id, activeStoreId()]);
    return $stmt->fetch() ?: null;
}

function getCategories(PDO $db): array
{
    $stmt = $db->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND store_id = ? ORDER BY category");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Stock

function recordStockAdjustment(PDO $db, int $productId, int $userId, string $userName, string $type, int $qty, int $prevStock, int $newStock, string $reason = ''): void
{
    $stmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$productId, $userId, $userName, $type, $qty, $prevStock, $newStock, $reason, activeStoreId()]);
}

function getStockHistory(PDO $db, int $productId): array
{
    $stmt = $db->prepare("SELECT * FROM stock_adjustments WHERE product_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$productId, activeStoreId()]);
    return $stmt->fetchAll();
}

// Reports

function salesReport(PDO $db, string $period): array
{
    $sql = "SELECT date(created_at) as day, COUNT(*) as transactions, SUM(total) as total, SUM(tax) as tax, SUM(discount) as discount FROM sales WHERE store_id = ?";

    if ($period === 'today') {
        $sql .= " AND date(created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY day ORDER BY day DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function salesByProduct(PDO $db, string $period): array
{
    $sql = "SELECT si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total, SUM(si.cost_price * si.quantity) as cost FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY si.product_name ORDER BY total DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function salesByCashier(PDO $db, string $period): array
{
    $sql = "SELECT s.cashier_name, COUNT(*) as transactions, SUM(s.total) as total FROM sales s WHERE s.store_id = ?";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY s.cashier_name ORDER BY total DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function profitReport(PDO $db, string $period): array
{
    $sql = "SELECT date(s.created_at) as day, SUM(si.total) as revenue, SUM(si.cost_price * si.quantity) as cost, SUM(si.total) - SUM(si.cost_price * si.quantity) as profit FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY day ORDER BY day DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function inventoryReport(PDO $db): array
{
    $stmt = $db->prepare("SELECT *, (stock_quantity * cost_price) as stock_value FROM products WHERE store_id = ? ORDER BY name ASC");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function generateReceiptNumber(PDO $db): string
{
    $prefix = 'RCP-' . date('Ymd') . '-';
    $suffix = strtoupper(bin2hex(random_bytes(5)));
    return $prefix . $suffix;
}

function getSalesForExport(PDO $db, string $period): array
{
    $sql = "SELECT s.receipt_number, s.cashier_name, s.subtotal, s.tax, s.discount, s.total, s.payment_method, s.created_at FROM sales s WHERE s.store_id = ?";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
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
    $sql = "SELECT si.product_name, si.quantity, si.price, si.total, s.receipt_number, s.created_at FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?";

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

// Stores
function getStores(PDO $db): array
{
    return $db->query("SELECT * FROM stores ORDER BY name ASC")->fetchAll();
}

function getStore(PDO $db, int $id): ?array
{
    $stmt = $db->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function activeStoreId(): int
{
    return (int) ($_SESSION['store_id'] ?? ACTIVE_STORE_ID);
}

function getStoreDashboardData(PDO $db, int $storeId): array
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ?");
    $stmt->execute([$storeId]);
    $sales = $stmt->fetch();

    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active' AND store_id = ?");
    $stmt->execute([$storeId]);
    $lowStock = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?");
    $stmt->execute([$storeId]);
    $productCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE store_id = ? AND status = 'active'");
    $stmt->execute([$storeId]);
    $userCount = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE store_id = ?");
    $stmt->execute([$storeId]);
    $totalTransactions = (int) $stmt->fetchColumn();

    return [
        'today_sales' => (float) $sales['total'],
        'today_transactions' => (int) $sales['count'],
        'low_stock' => $lowStock,
        'product_count' => $productCount,
        'user_count' => $userCount,
        'total_transactions' => $totalTransactions,
    ];
}

// Audit Logs
function logAction(PDO $db, string $action, string $entityType = null, int $entityId = null, string $details = null): void
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) return;
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, store_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        (int) $user['id'],
        $user['full_name'] ?? $user['username'],
        $user['role'] ?? '',
        isset($user['store_id']) ? (int) $user['store_id'] : activeStoreId(),
        $action,
        $entityType,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ]);
}

function getAuditLogs(PDO $db, string $search = '', string $action = '', string $entityType = '', string $from = '', string $to = '', string $sort = 'created_at', string $dir = 'DESC', int $page = 1, int $perPage = 50): array
{
    $sql = "SELECT al.*, s.name as store_name FROM audit_logs al LEFT JOIN stores s ON s.id = al.store_id WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (al.user_name LIKE ? OR al.details LIKE ? OR al.action LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($action) {
        $sql .= " AND al.action = ?";
        $params[] = $action;
    }
    if ($entityType) {
        $sql .= " AND al.entity_type = ?";
        $params[] = $entityType;
    }
    if ($from) {
        $sql .= " AND al.created_at >= ?";
        $params[] = $from . ' 00:00:00';
    }
    if ($to) {
        $sql .= " AND al.created_at <= ?";
        $params[] = $to . ' 23:59:59';
    }

    $allowedSort = ['created_at', 'user_name', 'action', 'entity_type'];
    $sort = in_array($sort, $allowedSort, true) ? $sort : 'created_at';
    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

    $countSql = str_replace("SELECT al.*, s.name as store_name", "SELECT COUNT(*)", $sql);
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $sql .= " ORDER BY al.$sort $dir LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'perPage' => $perPage];
}

function getAuditLogActions(PDO $db): array
{
    return $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
}

function getAuditLogEntityTypes(PDO $db): array
{
    return $db->query("SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type IS NOT NULL ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
}

function exportAuditLogsCsv(PDO $db, string $search = '', string $action = '', string $entityType = '', string $from = '', string $to = ''): void
{
    $sql = "SELECT al.created_at, al.user_name, al.user_role, COALESCE(s.name,'') as store_name, al.action, al.entity_type, al.entity_id, al.details, al.ip_address FROM audit_logs al LEFT JOIN stores s ON s.id = al.store_id WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (al.user_name LIKE ? OR al.details LIKE ? OR al.action LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($action) { $sql .= " AND al.action = ?"; $params[] = $action; }
    if ($entityType) { $sql .= " AND al.entity_type = ?"; $params[] = $entityType; }
    if ($from) { $sql .= " AND al.created_at >= ?"; $params[] = $from . ' 00:00:00'; }
    if ($to) { $sql .= " AND al.created_at <= ?"; $params[] = $to . ' 23:59:59'; }
    $sql .= " ORDER BY al.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'User', 'Role', 'Store', 'Action', 'Entity Type', 'Entity ID', 'Details', 'IP Address']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'], $r['user_name'], $r['user_role'], $r['store_name'], $r['action'], $r['entity_type'], $r['entity_id'], $r['details'], $r['ip_address']]);
    }
    fclose($out);
    exit;
}

// Store Performance Rankings
function getStorePerformanceRankings(PDO $db, string $period = 'today'): array
{
    $dateFilter = match ($period) {
        'today' => "DATE(s.created_at) = CURDATE()",
        'week' => "s.created_at >= NOW() - INTERVAL 7 DAY",
        'month' => "s.created_at >= NOW() - INTERVAL 30 DAY",
        'year' => "s.created_at >= NOW() - INTERVAL 365 DAY",
        default => "DATE(s.created_at) = CURDATE()",
    };

    $sql = "SELECT st.id, st.name, st.currency,
            COALESCE(SUM(s.total), 0) as revenue,
            COUNT(s.id) as transactions,
            COALESCE(AVG(s.total), 0) as avg_transaction,
            (SELECT COUNT(*) FROM sale_items si JOIN sales s2 ON s2.id = si.sale_id WHERE s2.store_id = st.id AND $dateFilter) as items_sold
            FROM stores st
            LEFT JOIN sales s ON s.store_id = st.id AND $dateFilter
            GROUP BY st.id ORDER BY revenue DESC";
    return $db->query($sql)->fetchAll();
}

// System Health & Backup
function getSystemHealth(PDO $db): array
{
    $health = [];

    // Database status
    try {
        $db->query("SELECT 1");
        $health['database'] = ['status' => 'healthy', 'message' => 'Connected'];
    } catch (PDOException $e) {
        $health['database'] = ['status' => 'critical', 'message' => $e->getMessage()];
    }

    // Database size
    $stmt = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
    $row = $stmt->fetch();
    $health['db_size'] = ['status' => ((float) ($row['size_mb'] ?? 0)) > 1000 ? 'warning' : 'healthy', 'message' => ($row['size_mb'] ?? 0) . ' MB'];

    // Server uptime
    try {
        $stmt = $db->query("SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(NOW(), INTERVAL -VARIABLE_VALUE SECOND)) as uptime_seconds FROM performance_schema.global_status WHERE VARIABLE_NAME = 'Uptime'");
        $uptime = $stmt->fetch();
        $uptimeStr = 'Unknown';
        if ($uptime && $uptime['uptime_seconds'] > 0) {
            $days = floor((int)$uptime['uptime_seconds'] / 86400);
            $hours = floor(((int)$uptime['uptime_seconds'] % 86400) / 3600);
            $uptimeStr = "{$days}d {$hours}h";
        }
    } catch (PDOException $e) {
        $uptimeStr = 'N/A (permission)';
    }
    $health['server_uptime'] = ['status' => 'healthy', 'message' => $uptimeStr];

    // PHP memory
    $memUsage = memory_get_usage(true);
    $memPeak = memory_get_peak_usage(true);
    $memLimit = ini_get('memory_limit');
    $health['memory'] = ['status' => $memUsage > 100 * 1024 * 1024 ? 'warning' : 'healthy', 'message' => round($memUsage / 1024 / 1024, 2) . ' MB / ' . $memLimit . ' (peak: ' . round($memPeak / 1024 / 1024, 2) . ' MB)'];

    // Storage - backup directory
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        $health['storage'] = ['status' => 'warning', 'message' => 'Backup directory not found'];
    } else {
        $free = disk_free_space($backupDir);
        $total = disk_total_space($backupDir);
        $pct = $total > 0 ? round(($free / $total) * 100, 1) : 0;
        $health['storage'] = ['status' => $pct < 10 ? 'critical' : ($pct < 25 ? 'warning' : 'healthy'), 'message' => round($free / 1024 / 1024 / 1024, 2) . ' GB free of ' . round($total / 1024 / 1024 / 1024, 2) . ' GB'];
    }

    // Last backup
    $stmt = $db->query("SELECT * FROM backups WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1");
    $lastBackup = $stmt->fetch();
    if ($lastBackup) {
        $daysSince = floor((time() - strtotime($lastBackup['created_at'])) / 86400);
        $health['last_backup'] = ['status' => $daysSince > 7 ? 'warning' : 'healthy', 'message' => $lastBackup['filename'] . ' (' . $daysSince . ' days ago)', 'id' => $lastBackup['id']];
    } else {
        $health['last_backup'] = ['status' => 'critical', 'message' => 'No backup found'];
    }

    // Failed backups
    $stmt = $db->query("SELECT COUNT(*) FROM backups WHERE status = 'failed'");
    $failedCount = (int) $stmt->fetchColumn();
    $health['failed_backups'] = ['status' => $failedCount > 0 ? 'warning' : 'healthy', 'message' => $failedCount . ' failed'];

    return $health;
}

function runBackup(PDO $db): array
{
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            return ['success' => false, 'message' => 'Cannot create backup directory'];
        }
    }

    $filename = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    $mysqldump = 'mysqldump';
    $possiblePaths = ['C:\xampp\mysql\bin\mysqldump.exe', 'C:\wamp64\bin\mysql\mysql*\bin\mysqldump.exe', '/usr/bin/mysqldump'];
    foreach ($possiblePaths as $p) {
        if (file_exists($p)) { $mysqldump = '"' . $p . '"'; break; }
    }
    // Try glob pattern for versioned paths
    if ($mysqldump === 'mysqldump') {
        $glob = glob('C:\wamp64\bin\mysql\mysql*\bin\mysqldump.exe');
        if (!empty($glob)) $mysqldump = '"' . $glob[0] . '"';
    }

    $cmd = sprintf(
        '%s --host=%s --user=%s %s %s > %s 2>&1',
        $mysqldump,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        DB_PASS ? '--password=' . escapeshellarg(DB_PASS) : '',
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );

    $output = null;
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    $fileSize = file_exists($filepath) ? filesize($filepath) : 0;

    if ($returnCode === 0 && $fileSize > 0) {
        $stmt = $db->prepare("INSERT INTO backups (filename, file_size, status, type) VALUES (?, ?, 'completed', 'manual')");
        $stmt->execute([$filename, $fileSize]);
        return ['success' => true, 'message' => 'Backup created: ' . $filename, 'file' => $filename, 'size' => $fileSize];
    }

    $stmt = $db->prepare("INSERT INTO backups (filename, file_size, status, type) VALUES (?, ?, 'failed', 'manual')");
    $stmt->execute([$filename, $fileSize]);
    return ['success' => false, 'message' => 'Backup failed: ' . implode("\n", $output ?? [])];
}

function getBackupHistory(PDO $db, int $limit = 20): array
{
    $stmt = $db->prepare("SELECT * FROM backups ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// CSRF
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function validate_csrf(string $token): bool
{
    return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

// Client IP
function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Return processing
function processReturnApproval(PDO $db, array $items, string $reason, string $resolution, ?int $exchangeProductId, int $exchangeQty, array $user): void
{
    $adjStmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($reason === 'return') {
        foreach ($items as $item) {
            $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
            $pStmt->execute([(int) $item['product_id'], activeStoreId()]);
            $prod = $pStmt->fetch();
            if (!$prod) {
                throw new RuntimeException("Product ID {$item['product_id']} not found in this store");
            }
            $prevStock = (int) ($prod['stock_quantity'] ?? 0);
            $newStock = $prevStock + (int) $item['qty'];

            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND store_id = ?")
               ->execute([(int) $item['qty'], (int) $item['product_id'], activeStoreId()]);

            $adjStmt->execute([(int) $item['product_id'], (int) $user['id'], $user['full_name'] ?? $user['username'], 'return', (int) $item['qty'], $prevStock, $newStock, 'Return approved - ' . $reason, activeStoreId()]);
        }

        if ($resolution === 'exchange' && $exchangeProductId) {
            $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
            $pStmt->execute([$exchangeProductId, activeStoreId()]);
            $prod = $pStmt->fetch();
            if (!$prod) {
                throw new RuntimeException("Exchange product ID $exchangeProductId not found in this store");
            }
            $prevStock = (int) ($prod['stock_quantity'] ?? 0);
            $newStock = $prevStock - $exchangeQty;

            $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ?")
               ->execute([$exchangeQty, $exchangeProductId, activeStoreId()]);

            $adjStmt->execute([$exchangeProductId, (int) $user['id'], $user['full_name'] ?? $user['username'], 'exchange', $exchangeQty, $prevStock, $newStock, 'Exchange for return', activeStoreId()]);
        }
    } elseif ($reason === 'damage') {
        foreach ($items as $item) {
            $pStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ? AND store_id = ?");
            $pStmt->execute([(int) $item['product_id'], activeStoreId()]);
            $prod = $pStmt->fetch();
            if (!$prod) {
                throw new RuntimeException("Product ID {$item['product_id']} not found in this store");
            }
            $prevStock = (int) ($prod['stock_quantity'] ?? 0);
            $newStock = $prevStock - (int) $item['qty'];

            $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ?")
               ->execute([(int) $item['qty'], (int) $item['product_id'], activeStoreId()]);

            $adjStmt->execute([(int) $item['product_id'], (int) $user['id'], $user['full_name'] ?? $user['username'], 'damage', (int) $item['qty'], $prevStock, $newStock, 'Damaged item written off', activeStoreId()]);
        }
    }
}

// Rate limiting
function rate_limit_check_sliding(PDO $db, string $identifier, string $action, int $maxRequests, int $windowSeconds): int
{
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ? AND action = ? AND created_at > ?");
    $stmt->execute([$identifier, $action, $cutoff]);
    $count = (int) $stmt->fetchColumn();
    return max(0, $maxRequests - $count);
}

function rate_limit_hit(PDO $db, string $identifier, string $action): void
{
    $stmt = $db->prepare("INSERT INTO rate_limits (identifier, action) VALUES (?, ?)");
    $stmt->execute([$identifier, $action]);
}

// Honeypot anti-spam
function honeypot_field(): string
{
    return '<div style="display:none"><input type="text" name="_hp" value="" autocomplete="off"></div>';
}

function honeypot_validate(): ?string
{
    return !empty($_POST['_hp']) ? 'Spam detected.' : null;
}

// Simple math CAPTCHA
function captcha_render(): string
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['_captcha'] = $a + $b;
    return '<div class="form-group"><label for="_captcha">What is ' . $a . ' + ' . $b . '?</label>'
        . '<input type="number" name="_captcha" id="_captcha" class="form-control" required autocomplete="off"></div>';
}

function captcha_validate(): bool
{
    $answer = (int) ($_POST['_captcha'] ?? 0);
    return isset($_SESSION['_captcha']) && $answer === (int) $_SESSION['_captcha'];
}

// Password reset token management
function generatePasswordResetToken(PDO $db, string $email): string
{
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $stmt = $db->prepare("REPLACE INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $token, $expires]);
    return $token;
}

function verifyPasswordResetToken(PDO $db, string $token): ?int
{
    $stmt = $db->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ? (int) $row['user_id'] : null;
}

function verifyEmailToken(PDO $db, string $token): ?int
{
    $stmt = $db->prepare("SELECT user_id FROM email_verifications WHERE token = ? AND expires_at > NOW() AND used = 0");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        $userId = (int) $row['user_id'];
        $db->prepare("UPDATE email_verifications SET used = 1 WHERE token = ?")->execute([$token]);
        $db->prepare("UPDATE users SET status = 'pending' WHERE id = ? AND status = 'unverified'")->execute([$userId]);
        return $userId;
    }
    return null;
}

function resetPassword(PDO $db, int $userId, string $password): void
{
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    $db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?")->execute([$userId]);
}

// Legacy alias for logAction
function logActivity(PDO $db, int $userId, string $userName, string $action, string $details): void
{
    logAction($db, $action, null, $userId ?: null, $details);
}
