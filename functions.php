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

// ============================================================
// PERMISSION HELPERS
// ============================================================

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isSuperAdmin(): bool
{
    return !empty($_SESSION['is_super_admin']);
}

function isStoreAdmin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'store_admin';
}

function isCashier(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'cashier';
}

function isManager(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'manager';
}

function currentUserRole(): string
{
    return $_SESSION['user_role'] ?? '';
}

function canManageReminder(PDO $db, int $reminderId): bool
{
    if (isSuperAdmin()) return true;
    $stmt = $db->prepare("SELECT * FROM calendar_reminders WHERE id = ?");
    $stmt->execute([$reminderId]);
    $reminder = $stmt->fetch();
    if (!$reminder) return false;
    $storeId = currentUserStoreId();
    if (in_array(userRole(), ['store_admin', 'manager'], true) && (int) $reminder['store_id'] === $storeId) return true;
    if (isCashier() && (int) $reminder['assigned_to_user_id'] === CURRENT_USER_ID) return true;
    return false;
}

function currentUserStoreId(): ?int
{
    if (isSuperAdmin()) {
        return (int) ($_SESSION['store_id'] ?? 0) ?: null;
    }
    return isset($_SESSION['user_store_id']) ? (int) $_SESSION['user_store_id'] : null;
}

function canAccessStore(?int $storeId): bool
{
    if (isSuperAdmin()) {
        return true;
    }
    if ($storeId === null) {
        return false;
    }
    $userStoreId = currentUserStoreId();
    return $userStoreId !== null && $storeId === $userStoreId;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('index.php?page=login');
    }
}

function requireSuperAdmin(): void
{
    requireLogin();
    if (!isSuperAdmin()) {
        accessDenied();
    }
}

function requireStoreAccess(?int $storeId): void
{
    requireLogin();
    if (!canAccessStore($storeId)) {
        accessDenied();
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $roles, true)) {
        accessDenied();
    }
}

function accessDenied(): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <link rel="stylesheet" href="../style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg); font-family:'Inter',sans-serif; margin:0; }
            .denied-box { text-align:center; padding:40px; max-width:420px; }
            .denied-box i { font-size:64px; color:#ef4444; margin-bottom:20px; }
            .denied-box h1 { font-size:28px; margin-bottom:8px; color:var(--text); }
            .denied-box p { color:var(--gray-400); margin-bottom:24px; line-height:1.6; }
        </style>
    </head>
    <body>
        <div class="denied-box">
            <i class="fas fa-shield-halved"></i>
            <h1>Access Denied</h1>
            <p>You do not have permission to access this page or resource. If you believe this is an error, please contact your system administrator.</p>
            <a href="index.php?page=dashboard" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function jsonAccessDenied(string $message = 'Access denied. You do not have permission to access this store.'): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function requireAjaxLogin(): void
{
    if (!isLoggedIn()) {
        jsonAccessDenied('Session expired. Please login again.');
    }
}

function requireAjaxStoreAccess(?int $storeId): void
{
    requireAjaxLogin();
    if (!canAccessStore($storeId)) {
        jsonAccessDenied();
    }
}

function userRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function userName(): ?string
{
    return $_SESSION['user_name'] ?? ($_SESSION['user']['display_name'] ?? $_SESSION['user']['full_name'] ?? null);
}

// ============================================================
// AUTH
// ============================================================

function login(PDO $db, string $username, string $password, string $displayName = ''): bool
{
    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    $authenticated = false;
    $firebase = $GLOBALS['firebase'] ?? null;
    if (!empty($user['firebase_uid'])) {
        if (!$firebase instanceof FirebaseAuth || empty($user['email'])) {
            return false;
        }
        try {
            $firebaseUser = $firebase->signInWithPassword($user['email'], $password);
            $firebaseUid = (string) ($firebaseUser['localId'] ?? '');
            $authenticated = $firebaseUid !== '' && hash_equals((string) $user['firebase_uid'], $firebaseUid);
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
        }
    } else {
        // Temporary migration bridge for accounts created before Firebase.
        $authenticated = !empty($user['password']) && password_verify($password, $user['password']);
    }

    if (!$authenticated) return false;

    {
        session_regenerate_id(true);
        $role = $user['role'];
        $storeId = $user['store_id'] ? (int) $user['store_id'] : null;

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'display_name' => $displayName ?: $user['full_name'],
            'role' => $role,
            'store_id' => $storeId,
        ];
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $displayName ?: $user['full_name'];
        $_SESSION['user_role'] = $role;
        $_SESSION['user_store_id'] = $storeId;
        $_SESSION['is_super_admin'] = ($role === 'super_admin') ? true : false;

        if ($role === 'super_admin') {
            if (!empty($_SESSION['store_id'])) {
                // Keep the super_admin's current store selection
            } else {
                $stmt2 = $db->query("SELECT MIN(id) FROM stores");
                $firstStore = (int) $stmt2->fetchColumn();
                $_SESSION['store_id'] = $firstStore ?: 1;
            }
        } elseif ($role === 'store_admin' && $storeId) {
            $_SESSION['store_id'] = $storeId;
        } elseif ($storeId) {
            $_SESSION['store_id'] = $storeId;
        } else {
            $stmt2 = $db->query("SELECT MIN(id) FROM stores");
            $firstStore = (int) $stmt2->fetchColumn();
            $_SESSION['store_id'] = $firstStore ?: 1;
        }

        logAction($db, 'login', 'user', (int) $user['id'], 'User logged in: ' . $username . ' role: ' . $role);
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function hasRole(string $role): bool
{
    return ($_SESSION['user_role'] ?? '') === $role;
}

function activeStoreId(): int
{
    if (!isSuperAdmin() && isset($_SESSION['user_store_id'])) {
        return (int) $_SESSION['user_store_id'];
    }
    return (int) ($_SESSION['store_id'] ?? ACTIVE_STORE_ID);
}

function isCurrentStoreAdminStore(): bool
{
    if (!isStoreAdmin()) return false;
    return (int) ($_SESSION['store_id'] ?? 0) === (int) ($_SESSION['user_store_id'] ?? 0);
}

// ============================================================
// SUPER ADMIN COUNT CHECK
// ============================================================

function countSuperAdmins(PDO $db): int
{
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND status = 'active'");
    return (int) $stmt->fetchColumn();
}

function canCreateSuperAdmin(PDO $db): bool
{
    return countSuperAdmins($db) < 3;
}

function checkSuperAdminLimit(PDO $db): ?string
{
    if (!canCreateSuperAdmin($db)) {
        return 'Cannot create another Super Admin. The system already has 3 active Super Admin accounts. Please deactivate an existing Super Admin first or assign a different role.';
    }
    return null;
}

// ============================================================
// DASHBOARD
// ============================================================

function getTodaySales(PDO $db): array
{
    if (isSuperAdmin()) {
        $stmt = $db->query("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count, COALESCE(SUM(discount_amount),0) as discount_total FROM sales WHERE DATE(created_at) = CURDATE()");
        return $stmt->fetch();
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count, COALESCE(SUM(discount_amount),0) as discount_total FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ?");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetch();
}

function getBestSellers(PDO $db, int $limit = 5): array
{
    if (isSuperAdmin()) {
        $stmt = $db->query("
            SELECT si.product_id, si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            WHERE DATE(s.created_at) = CURDATE()
            GROUP BY si.product_id ORDER BY qty DESC LIMIT $limit
        ");
        return $stmt->fetchAll();
    }
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
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT * FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active' ORDER BY stock_quantity ASC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    $stmt = $db->prepare("SELECT * FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active' AND store_id = ? ORDER BY stock_quantity ASC LIMIT ?");
    $stmt->execute([activeStoreId(), $limit]);
    return $stmt->fetchAll();
}

function getAllLowStockProducts(PDO $db, int $limit = 20): array
{
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT p.*, s.name as store_name FROM products p JOIN stores s ON s.id = p.store_id WHERE p.stock_quantity <= p.low_stock_threshold AND p.status = 'active' ORDER BY p.stock_quantity ASC, s.name ASC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    $stmt = $db->prepare("SELECT p.*, s.name as store_name FROM products p JOIN stores s ON s.id = p.store_id WHERE p.stock_quantity <= p.low_stock_threshold AND p.status = 'active' AND p.store_id = ? ORDER BY p.stock_quantity ASC LIMIT ?");
    $stmt->execute([activeStoreId(), $limit]);
    return $stmt->fetchAll();
}

function getRecentSales(PDO $db, int $limit = 5, string $cashierName = ''): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT s.*, st.name as store_name FROM sales s JOIN stores st ON st.id = s.store_id";
        $params = [];
        if ($cashierName) {
            $sql .= " WHERE s.cashier_name = ?";
            $params[] = $cashierName;
        }
        $sql .= " ORDER BY s.created_at DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
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

// ============================================================
// PRODUCTS
// ============================================================

function getProducts(PDO $db, string $search = '', string $category = '', string $stockFilter = ''): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT p.*, s.name as store_name FROM products p JOIN stores s ON s.id = p.store_id WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($category) {
            $sql .= " AND p.category = ?";
            $params[] = $category;
        }
        if ($stockFilter === 'low') {
            $sql .= " AND p.stock_quantity <= p.low_stock_threshold";
        } elseif ($stockFilter === 'out') {
            $sql .= " AND p.stock_quantity = 0";
        }
        $sql .= " ORDER BY p.name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
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
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT p.*, s.name as store_name FROM products p JOIN stores s ON s.id = p.store_id WHERE p.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
    $stmt->execute([$id, activeStoreId()]);
    return $stmt->fetch() ?: null;
}

function getCategories(PDO $db): array
{
    if (isSuperAdmin()) {
        return $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    }
    $stmt = $db->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND store_id = ? ORDER BY category");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================
// STOCK
// ============================================================

function recordStockAdjustment(PDO $db, int $productId, int $userId, string $userName, string $type, int $qty, int $prevStock, int $newStock, string $reason = ''): void
{
    $stmt = $db->prepare("INSERT INTO stock_adjustments (product_id, user_id, user_name, type, quantity, previous_stock, new_stock, reason, store_id) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$productId, $userId, $userName, $type, $qty, $prevStock, $newStock, $reason, activeStoreId()]);
}

function getStockHistory(PDO $db, int $productId): array
{
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT sa.*, s.name as store_name FROM stock_adjustments sa JOIN stores s ON s.id = sa.store_id WHERE sa.product_id = ? ORDER BY sa.created_at DESC LIMIT 50");
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }
    $stmt = $db->prepare("SELECT * FROM stock_adjustments WHERE product_id = ? AND store_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$productId, activeStoreId()]);
    return $stmt->fetchAll();
}

// ============================================================
// REPORTS
// ============================================================

function salesReport(PDO $db, string $period): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT date(created_at) as day, COUNT(*) as transactions, SUM(total) as total, SUM(tax) as tax, SUM(discount) as discount, COALESCE(SUM(discount_amount),0) as discount_amount, COALESCE(SUM(subtotal_before_discount),0) as subtotal_before_discount FROM sales";
    } else {
        $sql = "SELECT date(created_at) as day, COUNT(*) as transactions, SUM(total) as total, SUM(tax) as tax, SUM(discount) as discount, COALESCE(SUM(discount_amount),0) as discount_amount, COALESCE(SUM(subtotal_before_discount),0) as subtotal_before_discount FROM sales WHERE store_id = ?";
    }

    if ($period === 'today') {
        $sql .= " AND date(created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY day ORDER BY day DESC";
    if (isSuperAdmin()) {
        $stmt = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
    }
    return $stmt->fetchAll();
}

function salesByProduct(PDO $db, string $period): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total, SUM(si.cost_price * si.quantity) as cost FROM sale_items si JOIN sales s ON s.id = si.sale_id";
    } else {
        $sql = "SELECT si.product_name, SUM(si.quantity) as qty, SUM(si.total) as total, SUM(si.cost_price * si.quantity) as cost FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?";
    }

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY si.product_name ORDER BY total DESC";
    if (isSuperAdmin()) {
        $stmt = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
    }
    return $stmt->fetchAll();
}

function salesByCashier(PDO $db, string $period): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT s.cashier_name, COUNT(*) as transactions, SUM(s.total) as total FROM sales s";
    } else {
        $sql = "SELECT s.cashier_name, COUNT(*) as transactions, SUM(s.total) as total FROM sales s WHERE s.store_id = ?";
    }

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY s.cashier_name ORDER BY total DESC";
    if (isSuperAdmin()) {
        $stmt = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
    }
    return $stmt->fetchAll();
}

function profitReport(PDO $db, string $period): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT date(s.created_at) as day, SUM(si.total) as revenue, SUM(si.cost_price * si.quantity) as cost, SUM(si.total) - SUM(si.cost_price * si.quantity) as profit FROM sale_items si JOIN sales s ON s.id = si.sale_id";
    } else {
        $sql = "SELECT date(s.created_at) as day, SUM(si.total) as revenue, SUM(si.cost_price * si.quantity) as cost, SUM(si.total) - SUM(si.cost_price * si.quantity) as profit FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?";
    }

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " GROUP BY day ORDER BY day DESC";
    if (isSuperAdmin()) {
        $stmt = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
    }
    return $stmt->fetchAll();
}

function inventoryReport(PDO $db): array
{
    if (isSuperAdmin()) {
        $stmt = $db->query("SELECT *, (stock_quantity * cost_price) as stock_value FROM products ORDER BY name ASC");
        return $stmt->fetchAll();
    }
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
    if (isSuperAdmin()) {
        $sql = "SELECT s.receipt_number, s.cashier_name, s.subtotal, s.discount as discount_pct, COALESCE(s.discount_code,'') as discount_code, COALESCE(s.discount_amount,0) as discount_amount, s.subtotal_before_discount, s.total_after_discount, s.tax, s.total, s.payment_method, s.created_at FROM sales s";
    } else {
        $sql = "SELECT s.receipt_number, s.cashier_name, s.subtotal, s.discount as discount_pct, COALESCE(s.discount_code,'') as discount_code, COALESCE(s.discount_amount,0) as discount_amount, s.subtotal_before_discount, s.total_after_discount, s.tax, s.total, s.payment_method, s.created_at FROM sales s WHERE s.store_id = ?";
    }

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " ORDER BY s.created_at DESC";
    if (isSuperAdmin()) {
        $stmt = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
    }
    return $stmt->fetchAll();
}

function getUnreadMessageCount(PDO $db, int $userId): int
{
    $userRole = $_SESSION['user_role'] ?? '';
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id != ? AND is_read = 0 AND store_id IS NOT NULL");
        $stmt->execute([$userId]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE sender_id != ? AND is_read = 0 AND store_id = ?");
        $stmt->execute([$userId, activeStoreId()]);
    }
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
    if (isSuperAdmin()) {
        return $db->query("SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.is_read = 0 AND m.store_id IS NOT NULL ORDER BY m.created_at DESC")->fetchAll();
    }
    $stmt = $db->prepare("SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.is_read = 0 AND m.store_id = ? ORDER BY m.created_at DESC");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function getAllMessages(PDO $db): array
{
    if (isSuperAdmin()) {
        return $db->query("SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id ORDER BY m.created_at DESC")->fetchAll();
    }
    $stmt = $db->prepare("SELECT m.*, u.username FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.store_id = ? ORDER BY m.created_at DESC");
    $stmt->execute([activeStoreId()]);
    return $stmt->fetchAll();
}

function getSalesItemsForExport(PDO $db, string $period): array
{
    if (isSuperAdmin()) {
        $sql = "SELECT si.product_name, si.quantity, si.price, si.total, s.receipt_number, s.created_at FROM sale_items si JOIN sales s ON s.id = si.sale_id";
    } else {
        $sql = "SELECT si.product_name, si.quantity, si.price, si.total, s.receipt_number, s.created_at FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.store_id = ?";
    }

    if ($period === 'today') {
        $sql .= " AND date(s.created_at) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 7 DAY";
    } elseif ($period === 'month') {
        $sql .= " AND s.created_at >= NOW() - INTERVAL 30 DAY";
    }

    $sql .= " ORDER BY s.created_at DESC";
    if (isSuperAdmin()) {
        $stmt = $db->query($sql);
    } else {
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
    }
    return $stmt->fetchAll();
}

// ============================================================
// STORES
// ============================================================

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

// ============================================================
// STORE SETTINGS (Customization)
// ============================================================

function getStoreSettings(PDO $db, int $storeId): array
{
    $stmt = $db->prepare("SELECT * FROM store_settings WHERE store_id = ?");
    $stmt->execute([$storeId]);
    $settings = $stmt->fetch() ?: [];
    if ($settings) {
        foreach (['allowed_payment_methods', 'dashboard_widgets', 'reminder_categories', 'staff_permissions', 'store_policies'] as $jsonField) {
            if (isset($settings[$jsonField]) && is_string($settings[$jsonField])) {
                $settings[$jsonField] = json_decode($settings[$jsonField], true) ?: [];
            }
        }
    }
    return $settings;
}

function saveStoreSettings(PDO $db, int $storeId, array $data): void
{
    // JSON fields
    foreach (['allowed_payment_methods', 'dashboard_widgets', 'reminder_categories', 'staff_permissions', 'store_policies'] as $jsonField) {
        if (isset($data[$jsonField]) && is_array($data[$jsonField])) {
            $data[$jsonField] = json_encode($data[$jsonField]);
        }
    }

    $check = $db->prepare("SELECT id FROM store_settings WHERE store_id = ?");
    $check->execute([$storeId]);
    $exists = $check->fetch();

    $columns = [
        'store_display_name', 'store_slogan', 'logo_path', 'receipt_logo_path',
        'accent_color', 'theme_mode', 'contact_number', 'email_address', 'physical_address',
        'trading_hours', 'pos_background_style', 'dashboard_welcome_message',
        'receipt_footer', 'return_policy', 'exchange_policy', 'refund_policy', 'layby_policy',
        'social_media_handles', 'whatsapp_number', 'thank_you_message',
        'show_cashier_name_on_receipt', 'show_discount_on_receipt', 'show_qr_on_receipt',
        'show_customer_details_on_receipt',
        'default_payment_method', 'allowed_payment_methods',
        'enable_cash_payments', 'enable_card_payments', 'enable_mobile_payments',
        'enable_discounts', 'enable_coupons', 'enable_held_sales', 'enable_returns',
        'enable_sale_sound', 'sale_sound_volume', 'show_product_images_on_pos',
        'product_grid_size', 'default_category', 'auto_focus_barcode',
        'daily_sales_target', 'weekly_sales_target', 'monthly_sales_target',
        'low_stock_threshold', 'cashier_discount_limit',
        'require_admin_approval_high_discount', 'max_discount_percentage',
        'allow_coupon_stacking', 'discount_mode', 'return_period_days',
        'enable_receipt_reprint', 'require_admin_approval_for_returns',
        'require_admin_approval_for_large_discounts',
        'dashboard_widgets', 'reminder_categories', 'staff_permissions', 'store_policies',
        'cashier_can_view_recent_sales', 'cashier_can_reprint_receipts',
        'cashier_can_process_returns', 'cashier_can_apply_discounts',
        'cashier_can_hold_sales', 'cashier_can_view_stock',
    ];

    $setParts = [];
    $params = [];
    foreach ($columns as $col) {
        if (array_key_exists($col, $data)) {
            $setParts[] = "$col = ?";
            $params[] = $data[$col];
        }
    }

    if (empty($setParts)) return;

    if ($exists) {
        $setParts[] = "updated_at = NOW()";
        $sql = "UPDATE store_settings SET " . implode(', ', $setParts) . " WHERE store_id = ?";
        $params[] = $storeId;
    } else {
        $cols = [];
        $placeholders = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $data)) {
                $cols[] = $col;
                $placeholders[] = '?';
            }
        }
        if (empty($cols)) return;
        $sql = "INSERT INTO store_settings (store_id, " . implode(', ', $cols) . ") VALUES (?, " . implode(', ', $placeholders) . ")";
        array_unshift($params, $storeId);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

function resetStoreSettingsToDefault(PDO $db, int $storeId): void
{
    $stmt = $db->prepare("DELETE FROM store_settings WHERE store_id = ?");
    $stmt->execute([$storeId]);
}

/**
 * Delete self-checkout photos whose 30-day retention period has expired.
 * The directory is deliberately limited to generated sale_<id> image names.
 */
function purgeExpiredCustomerPhotos(PDO $db): void
{
    $photoDir = __DIR__ . '/captured_photos';
    if (!is_dir($photoDir)) {
        return;
    }

    try {
        $stmt = $db->query("SELECT id, customer_photo_path FROM sales WHERE customer_photo_path IS NOT NULL AND customer_photo_delete_after <= NOW() LIMIT 200");
        $expiredSales = $stmt->fetchAll();
        $clear = $db->prepare("UPDATE sales SET customer_photo_path = NULL WHERE id = ?");

        foreach ($expiredSales as $sale) {
            $relativePath = (string) $sale['customer_photo_path'];
            if (!preg_match('#^captured_photos/sale_\\d+\\.(?:jpg|jpeg|png)$#', $relativePath)) {
                continue;
            }

            $file = __DIR__ . '/' . $relativePath;
            if (!is_file($file) || @unlink($file)) {
                $clear->execute([(int) $sale['id']]);
            }
        }

        // Remove legacy photos created before retention metadata was introduced.
        $cutoff = time() - (CUSTOMER_PHOTO_RETENTION_DAYS * 86400);
        foreach (glob($photoDir . '/sale_*.*') ?: [] as $file) {
            if (preg_match('/^sale_\\d+\\.(?:jpg|jpeg|png)$/', basename($file))
                && is_file($file)
                && filemtime($file) <= $cutoff) {
                @unlink($file);
            }
        }
    } catch (Throwable $e) {
        error_log('Customer photo retention cleanup failed.');
    }
}

function storeSetting(array $storeSettings, string $key, $default = null)
{
    return $storeSettings[$key] ?? $default;
}

// Check if a cashier permission is allowed for the current store
function cashierCan(string $permission): bool
{
    global $storeSettings;
    $permMap = [
        'view_recent_sales' => 'cashier_can_view_recent_sales',
        'reprint_receipts' => 'cashier_can_reprint_receipts',
        'process_returns' => 'cashier_can_process_returns',
        'apply_discounts' => 'cashier_can_apply_discounts',
        'hold_sales' => 'cashier_can_hold_sales',
        'view_stock' => 'cashier_can_view_stock',
    ];
    $key = $permMap[$permission] ?? null;
    if (!$key) return false;
    return !empty($storeSettings[$key]);
}

function getStoreDashboardData(PDO $db, int $storeId): array
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ?");
    $stmt->execute([$storeId]);
    $sales = $stmt->fetch();

    $stmt = $db->prepare("SELECT COALESCE(SUM(discount_amount),0) as total_discount FROM sales WHERE DATE(created_at) = CURDATE() AND store_id = ? AND discount_coupon_id IS NOT NULL");
    $stmt->execute([$storeId]);
    $discountRow = $stmt->fetch();

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
        'today_discounts' => (float) ($discountRow['total_discount'] ?? 0),
        'low_stock' => $lowStock,
        'product_count' => $productCount,
        'user_count' => $userCount,
        'total_transactions' => $totalTransactions,
    ];
}

// ============================================================
// AUDIT LOGS
// ============================================================

function logAction(PDO $db, string $action, string $entityType = null, int $entityId = null, string $details = null): void
{
    if (!isLoggedIn()) return;
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, store_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? 0,
        $_SESSION['user_name'] ?? '',
        $_SESSION['user_role'] ?? '',
        isStoreAdmin() ? currentUserStoreId() : activeStoreId(),
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

    // Store admins can only see their own store's audit logs
    if (isStoreAdmin()) {
        $sql .= " AND al.store_id = ?";
        $params[] = currentUserStoreId();
    }

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

    if (isStoreAdmin()) {
        $sql .= " AND al.store_id = ?";
        $params[] = currentUserStoreId();
    }

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

// ============================================================
// STORE PERFORMANCE RANKINGS
// ============================================================

function getStorePerformanceRankings(PDO $db, string $period = 'today'): array
{
    if (!isSuperAdmin()) {
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
                COALESCE(AVG(s.total), 0) as avg_transaction
                FROM stores st
                LEFT JOIN sales s ON s.store_id = st.id AND $dateFilter
                WHERE st.id = ?
                GROUP BY st.id ORDER BY revenue DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([activeStoreId()]);
        return $stmt->fetchAll();
    }

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

// ============================================================
// SYSTEM HEALTH & BACKUP
// ============================================================

function getSystemHealth(PDO $db): array
{
    $health = [];

    try {
        $db->query("SELECT 1");
        $health['database'] = ['status' => 'healthy', 'message' => 'Connected'];
    } catch (PDOException $e) {
        $health['database'] = ['status' => 'critical', 'message' => $e->getMessage()];
    }

    $stmt = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
    $row = $stmt->fetch();
    $health['db_size'] = ['status' => ((float) ($row['size_mb'] ?? 0)) > 1000 ? 'warning' : 'healthy', 'message' => ($row['size_mb'] ?? 0) . ' MB'];

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

    $memUsage = memory_get_usage(true);
    $memPeak = memory_get_peak_usage(true);
    $memLimit = ini_get('memory_limit');
    $health['memory'] = ['status' => $memUsage > 100 * 1024 * 1024 ? 'warning' : 'healthy', 'message' => round($memUsage / 1024 / 1024, 2) . ' MB / ' . $memLimit . ' (peak: ' . round($memPeak / 1024 / 1024, 2) . ' MB)'];

    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        $health['storage'] = ['status' => 'warning', 'message' => 'Backup directory not found'];
    } else {
        $free = disk_free_space($backupDir);
        $total = disk_total_space($backupDir);
        $pct = $total > 0 ? round(($free / $total) * 100, 1) : 0;
        $health['storage'] = ['status' => $pct < 10 ? 'critical' : ($pct < 25 ? 'warning' : 'healthy'), 'message' => round($free / 1024 / 1024 / 1024, 2) . ' GB free of ' . round($total / 1024 / 1024 / 1024, 2) . ' GB'];
    }

    $stmt = $db->query("SELECT * FROM backups WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1");
    $lastBackup = $stmt->fetch();
    if ($lastBackup) {
        $daysSince = floor((time() - strtotime($lastBackup['created_at'])) / 86400);
        $health['last_backup'] = ['status' => $daysSince > 7 ? 'warning' : 'healthy', 'message' => $lastBackup['filename'] . ' (' . $daysSince . ' days ago)', 'id' => $lastBackup['id']];
    } else {
        $health['last_backup'] = ['status' => 'critical', 'message' => 'No backup found'];
    }

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

// ============================================================
// CSRF
// ============================================================

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

// ============================================================
// CLIENT IP
// ============================================================

function getClientIp(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trustedProxies = array_filter(array_map('trim', explode(',', getenv('POS_TRUSTED_PROXIES') ?: '')));
    if (in_array($remote, $trustedProxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $remote;
}

// ============================================================
// RETURN PROCESSING
// ============================================================

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

// ============================================================
// RATE LIMITING
// ============================================================

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

// ============================================================
// HONEYPOT & CAPTCHA
// ============================================================

function honeypot_field(): string
{
    return '<div style="display:none"><input type="text" name="_hp" value="" autocomplete="off"></div>';
}

function honeypot_validate(): ?string
{
    return !empty($_POST['_hp']) ? 'Spam detected.' : null;
}

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

// ============================================================
// PASSWORD RESET
// ============================================================

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

function logActivity(PDO $db, int $userId, string $userName, string $action, string $details): void
{
    logAction($db, $action, null, $userId ?: null, $details);
}

// ============================================================
// COUPON / DISCOUNT FUNCTIONS
// ============================================================

function validateCoupon(PDO $db, string $code, float $subtotal, ?int $storeId = null): array
{
    $stmt = $db->prepare("SELECT * FROM discount_coupons WHERE code = ?");
    $stmt->execute([strtoupper(trim($code))]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['success' => false, 'message' => 'Coupon not found.'];
    }

    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    if ($coupon['status'] === 'inactive') {
        return ['success' => false, 'message' => 'This coupon is inactive.'];
    }

    if ($coupon['status'] === 'expired') {
        return ['success' => false, 'message' => 'This coupon has expired.'];
    }

    if ($coupon['start_date'] && $today < $coupon['start_date']) {
        return ['success' => false, 'message' => 'This coupon is not yet valid.'];
    }

    if ($coupon['end_date'] && $today > $coupon['end_date']) {
        return ['success' => false, 'message' => 'This coupon has expired.'];
    }

    // Auto-expire if past end_date
    if ($coupon['end_date'] && $today > $coupon['end_date'] && $coupon['status'] === 'active') {
        $db->prepare("UPDATE discount_coupons SET status = 'expired' WHERE id = ?")->execute([$coupon['id']]);
        $coupon['status'] = 'expired';
        return ['success' => false, 'message' => 'This coupon has expired.'];
    }

    // Store check
    $couponStoreId = $coupon['store_id'] ? (int) $coupon['store_id'] : null;
    if ($couponStoreId !== null && $storeId !== null && $couponStoreId !== $storeId) {
        return ['success' => false, 'message' => 'This coupon is not valid for this store.'];
    }

    // Minimum spend
    $minSpend = (float) ($coupon['minimum_spend'] ?? 0);
    if ($minSpend > 0 && $subtotal < $minSpend) {
        return ['success' => false, 'message' => 'Minimum spend of ' . CURRENCY . ' ' . number_format($minSpend, 2) . ' required for this coupon.'];
    }

    // Usage limit
    $usageLimit = (int) ($coupon['usage_limit'] ?? 0);
    $usedCount = (int) ($coupon['used_count'] ?? 0);
    if ($usageLimit > 0 && $usedCount >= $usageLimit) {
        return ['success' => false, 'message' => 'This coupon has reached its usage limit.'];
    }

    return ['success' => true, 'coupon' => $coupon];
}

function calculateCouponDiscount(array $coupon, float $subtotal, array $cartItems = []): array
{
    $discountType = $coupon['discount_type'];
    $discountValue = (float) $coupon['discount_value'];
    $appliesTo = $coupon['applies_to'] ?? 'entire_sale';
    $maxDiscount = $coupon['maximum_discount'] ? (float) $coupon['maximum_discount'] : null;

    $discountAmount = 0.0;
    $applicableTotal = $subtotal;

    if ($appliesTo === 'product' && !empty($coupon['product_id']) && !empty($cartItems)) {
        $applicableTotal = 0;
        foreach ($cartItems as $item) {
            if ((int) $item['product_id'] === (int) $coupon['product_id']) {
                $applicableTotal += (float) $item['price'] * (int) ($item['qty'] ?? 1);
            }
        }
    } elseif ($appliesTo === 'category' && !empty($coupon['category_id']) && !empty($cartItems)) {
        $applicableTotal = 0;
        foreach ($cartItems as $item) {
            if (isset($item['category']) && $item['category'] === $coupon['category_id']) {
                $applicableTotal += (float) $item['price'] * (int) ($item['qty'] ?? 1);
            }
        }
    }

    if ($discountType === 'percentage') {
        $pct = min(100, max(0, $discountValue));
        $discountAmount = $applicableTotal * ($pct / 100);
    } else {
        $discountAmount = min($discountValue, $applicableTotal);
    }

    // Apply max discount cap
    if ($maxDiscount !== null && $discountAmount > $maxDiscount) {
        $discountAmount = $maxDiscount;
    }

    // Ensure discount cannot exceed subtotal
    $discountAmount = min($discountAmount, $subtotal);

    return [
        'discount_amount' => round($discountAmount, 2),
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'subtotal_before_discount' => $subtotal,
        'total_after_discount' => round($subtotal - $discountAmount, 2),
    ];
}

function incrementCouponUsage(PDO $db, int $couponId): void
{
    $db->prepare("UPDATE discount_coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
}

function getCoupons(PDO $db, array $filters = []): array
{
    $sql = "SELECT c.*, u.full_name as created_by_name, s.name as store_name 
            FROM discount_coupons c 
            LEFT JOIN users u ON u.id = c.created_by_user_id 
            LEFT JOIN stores s ON s.id = c.store_id 
            WHERE 1=1";
    $params = [];

    if (!isSuperAdmin()) {
        $storeId = activeStoreId();
        $sql .= " AND (c.store_id IS NULL OR c.store_id = ?)";
        $params[] = $storeId;
    }

    if (!empty($filters['store_id'])) {
        $sql .= " AND c.store_id = ?";
        $params[] = (int) $filters['store_id'];
    }

    if (!empty($filters['status'])) {
        $sql .= " AND c.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['discount_type'])) {
        $sql .= " AND c.discount_type = ?";
        $params[] = $filters['discount_type'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (c.code LIKE ? OR c.name LIKE ?)";
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['from_date'])) {
        $sql .= " AND c.created_at >= ?";
        $params[] = $filters['from_date'] . ' 00:00:00';
    }

    if (!empty($filters['to_date'])) {
        $sql .= " AND c.created_at <= ?";
        $params[] = $filters['to_date'] . ' 23:59:59';
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCoupon(PDO $db, int $id): ?array
{
    $sql = "SELECT c.*, u.full_name as created_by_name, s.name as store_name 
            FROM discount_coupons c 
            LEFT JOIN users u ON u.id = c.created_by_user_id 
            LEFT JOIN stores s ON s.id = c.store_id 
            WHERE c.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getCouponUsageStats(PDO $db, ?int $couponId = null): array
{
    $sql = "SELECT 
                COALESCE(SUM(discount_amount), 0) as total_discount_given,
                COUNT(*) as times_used,
                COALESCE(SUM(total_after_discount), 0) as total_revenue_after_discount
            FROM sales WHERE discount_coupon_id IS NOT NULL";
    $params = [];

    if ($couponId) {
        $sql .= " AND discount_coupon_id = ?";
        $params[] = $couponId;
    }

    if (!isSuperAdmin()) {
        $sql .= " AND store_id = ?";
        $params[] = activeStoreId();
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function getMostUsedCoupons(PDO $db, string $period = 'today', int $limit = 10): array
{
    $dateFilter = match ($period) {
        'today' => "DATE(s.created_at) = CURDATE()",
        'week' => "s.created_at >= NOW() - INTERVAL 7 DAY",
        'month' => "s.created_at >= NOW() - INTERVAL 30 DAY",
        default => "DATE(s.created_at) = CURDATE()",
    };

    $sql = "SELECT c.id, c.code, c.name, c.discount_type, c.discount_value,
                   COUNT(s.id) as usage_count,
                   COALESCE(SUM(s.discount_amount), 0) as total_discount_given
            FROM discount_coupons c
            JOIN sales s ON s.discount_coupon_id = c.id AND $dateFilter
            WHERE s.discount_coupon_id IS NOT NULL";

    $params = [];
    if (!isSuperAdmin()) {
        $sql .= " AND s.store_id = ?";
        $params[] = activeStoreId();
    }

    $sql .= " GROUP BY c.id ORDER BY usage_count DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDiscountImpact(PDO $db, string $period = 'today'): array
{
    $dateFilter = match ($period) {
        'today' => "DATE(created_at) = CURDATE()",
        'week' => "created_at >= NOW() - INTERVAL 7 DAY",
        'month' => "created_at >= NOW() - INTERVAL 30 DAY",
        default => "DATE(created_at) = CURDATE()",
    };

    $sql = "SELECT 
                COALESCE(SUM(CASE WHEN discount_coupon_id IS NOT NULL THEN total ELSE 0 END), 0) as discounted_revenue,
                COALESCE(SUM(CASE WHEN discount_coupon_id IS NULL THEN total ELSE 0 END), 0) as full_price_revenue,
                COALESCE(SUM(discount_amount), 0) as total_discount_given,
                COUNT(CASE WHEN discount_coupon_id IS NOT NULL THEN 1 END) as discounted_transactions,
                COUNT(*) as total_transactions
            FROM sales WHERE $dateFilter";

    $params = [];
    if (!isSuperAdmin()) {
        $sql .= " AND store_id = ?";
        $params[] = activeStoreId();
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function getCouponPerformanceByCashier(PDO $db, string $period = 'today'): array
{
    $dateFilter = match ($period) {
        'today' => "DATE(s.created_at) = CURDATE()",
        'week' => "s.created_at >= NOW() - INTERVAL 7 DAY",
        'month' => "s.created_at >= NOW() - INTERVAL 30 DAY",
        default => "DATE(s.created_at) = CURDATE()",
    };

    $sql = "SELECT s.cashier_name,
                   COUNT(*) as coupon_transactions,
                   COALESCE(SUM(s.discount_amount), 0) as total_discount_given,
                   COALESCE(SUM(s.total_after_discount), 0) as total_revenue
            FROM sales s
            WHERE s.discount_coupon_id IS NOT NULL AND $dateFilter";

    $params = [];
    if (!isSuperAdmin()) {
        $sql .= " AND s.store_id = ?";
        $params[] = activeStoreId();
    }

    $sql .= " GROUP BY s.cashier_name ORDER BY total_discount_given DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ============================================================
// CASHIER-SPECIFIC FUNCTIONS
// ============================================================

function getCashierSalesStats(PDO $db, int $userId, int $storeId): array
{
    $stmt = $db->prepare("SELECT 
                COALESCE(SUM(total), 0) as total_sales,
                COUNT(*) as transaction_count,
                COALESCE(AVG(total), 0) as average_sale,
                COALESCE(SUM(discount_amount), 0) as total_discounts
            FROM sales 
            WHERE cashier_id = ? AND store_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$userId, $storeId]);
    $today = $stmt->fetch();

    $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE cashier_id = ? AND store_id = ?");
    $stmt->execute([$userId, $storeId]);
    $completed = (int) $stmt->fetchColumn();

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM held_sales WHERE user_id = ? AND store_id = ?");
        $stmt->execute([$userId, $storeId]);
        $held = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Fallback if user_id column doesn't exist yet
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM held_sales WHERE cashier_id = ? AND store_id = ?");
            $stmt->execute([$userId, $storeId]);
            $held = (int) $stmt->fetchColumn();
        } catch (PDOException $e2) {
            $held = 0;
        }
    }

    return [
        'today_sales' => (float) ($today['total_sales'] ?? 0),
        'today_transactions' => (int) ($today['transaction_count'] ?? 0),
        'average_sale' => (float) ($today['average_sale'] ?? 0),
        'completed_sales' => $completed,
        'held_sales' => $held,
        'today_discounts' => (float) ($today['total_discounts'] ?? 0),
    ];
}

function getCashierRecentSales(PDO $db, int $userId, int $storeId, int $limit = 10): array
{
    $stmt = $db->prepare("SELECT id, receipt_number, total, discount, discount_amount, discount_code, 
                payment_method, created_at 
            FROM sales 
            WHERE cashier_id = ? AND store_id = ? 
            ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $storeId, $limit]);
    return $stmt->fetchAll();
}

function getCashierTodaySales(PDO $db, int $userId, int $storeId): array
{
    $stmt = $db->prepare("SELECT id, receipt_number, total, discount_amount, discount_code, 
                payment_method, created_at 
            FROM sales 
            WHERE cashier_id = ? AND store_id = ? AND DATE(created_at) = CURDATE() 
            ORDER BY created_at DESC");
    $stmt->execute([$userId, $storeId]);
    return $stmt->fetchAll();
}

// Held sales
function holdSale(PDO $db, int $userId, int $storeId, string $cartJson, float $subtotal): int
{
    $cashierName = userName() ?? 'Cashier';
    $stmt = $db->prepare("INSERT INTO held_sales (user_id, cashier_id, cashier_name, store_id, cart_data, items, subtotal, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$userId, $userId, $cashierName, $storeId, $cartJson, $cartJson, $subtotal]);
    return (int) $db->lastInsertId();
}

function getHeldSales(PDO $db, int $userId, int $storeId): array
{
    try {
        $stmt = $db->prepare("SELECT * FROM held_sales WHERE user_id = ? AND store_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId, $storeId]);
    } catch (PDOException $e) {
        // Fallback: use cashier_id if user_id column doesn't exist yet
        $stmt = $db->prepare("SELECT * FROM held_sales WHERE cashier_id = ? AND store_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId, $storeId]);
    }
    $rows = $stmt->fetchAll();
    // Map items→cart_data for backward compatibility with old schema
    foreach ($rows as &$row) {
        if (empty($row['cart_data']) && !empty($row['items'])) {
            $row['cart_data'] = is_string($row['items']) ? $row['items'] : json_encode($row['items']);
        }
    }
    return $rows;
}

function getHeldSaleById(PDO $db, int $id, int $userId, int $storeId): ?array
{
    try {
        $stmt = $db->prepare("SELECT * FROM held_sales WHERE id = ? AND user_id = ? AND store_id = ?");
        $stmt->execute([$id, $userId, $storeId]);
    } catch (PDOException $e) {
        $stmt = $db->prepare("SELECT * FROM held_sales WHERE id = ? AND cashier_id = ? AND store_id = ?");
        $stmt->execute([$id, $userId, $storeId]);
    }
    $row = $stmt->fetch();
    if ($row) {
        if (empty($row['cart_data']) && !empty($row['items'])) {
            $row['cart_data'] = is_string($row['items']) ? $row['items'] : json_encode($row['items']);
        }
    }
    return $row ?: null;
}

// ============================================================
// BARCODE FUNCTIONS
// ============================================================

function getBarcodeTypes(): array
{
    return [
        'UPC-A' => 'UPC-A',
        'UPC-E' => 'UPC-E',
        'EAN-13' => 'EAN-13',
        'EAN-8' => 'EAN-8',
        'ISBN' => 'ISBN',
        'ISSN' => 'ISSN',
        'Codabar' => 'Codabar',
        'ITF-14' => 'ITF-14',
        'Code 128' => 'Code 128',
        'Code 39' => 'Code 39',
        'QR Code' => 'QR Code',
        'DataMatrix' => 'DataMatrix',
        'Aztec Code' => 'Aztec Code',
        'MaxiCode' => 'MaxiCode',
        'PDF417' => 'PDF417',
    ];
}

function getRecommendedBarcodeTypes(): array
{
    return [
        'EAN-13' => 'EAN-13 (retail, standard)',
        'UPC-A' => 'UPC-A (retail, US)',
        'UPC-E' => 'UPC-E (retail, compact)',
        'EAN-8' => 'EAN-8 (retail, compact)',
        'Code 128' => 'Code 128 (internal/logistics)',
        'Code 39' => 'Code 39 (industrial)',
        'QR Code' => 'QR Code (product info/link)',
    ];
}

function isBarcodeTypeAvailable(string $type): bool
{
    return array_key_exists($type, getBarcodeTypes());
}

function validateBarcodeFormat(string $barcode, string $type): array
{
    $clean = preg_replace('/[^0-9A-Za-z\-]/', '', $barcode);
    $len = strlen($clean);

    switch ($type) {
        case 'UPC-A':
            return ['valid' => $len === 12 && preg_match('/^\d{12}$/', $clean), 'expected' => '12 digits'];
        case 'UPC-E':
            return ['valid' => $len === 8 && preg_match('/^\d{8}$/', $clean), 'expected' => '8 digits'];
        case 'EAN-13':
            return ['valid' => $len === 13 && preg_match('/^\d{13}$/', $clean), 'expected' => '13 digits'];
        case 'EAN-8':
            return ['valid' => $len === 8 && preg_match('/^\d{8}$/', $clean), 'expected' => '8 digits'];
        case 'ISBN':
            $isbn10 = preg_match('/^\d{10}$/', $clean);
            $isbn13 = preg_match('/^\d{13}$/', $clean);
            $isbn10x = preg_match('/^\d{9}[\dX]$/', $clean);
            return ['valid' => $isbn10 || $isbn13 || $isbn10x, 'expected' => '10 or 13 digits (ISBN-10 or ISBN-13)'];
        case 'ISSN':
            return ['valid' => $len === 8 && preg_match('/^\d{7}[\dX]$/', $clean), 'expected' => '8 characters (7 digits + check)'];
        case 'Codabar':
            return ['valid' => $len >= 4 && $len <= 16 && preg_match('/^[ABCD][0-9:\-\.\$\/\+]+[ABCD]$/', $clean), 'expected' => '4-16 chars, starts/ends with A-D'];
        case 'ITF-14':
            return ['valid' => $len === 14 && preg_match('/^\d{14}$/', $clean), 'expected' => '14 digits'];
        case 'Code 128':
            return ['valid' => $len >= 1 && $len <= 80, 'expected' => '1-80 characters'];
        case 'Code 39':
            return ['valid' => $len >= 1 && $len <= 50 && preg_match('/^[A-Z0-9\-\.\$\&\+]+$/', $clean), 'expected' => '1-50 uppercase alphanumeric'];
        case 'QR Code':
            return ['valid' => $len >= 1 && $len <= 4296, 'expected' => '1-4296 characters'];
        case 'DataMatrix':
            return ['valid' => $len >= 1 && $len <= 3116, 'expected' => '1-3116 characters'];
        case 'Aztec Code':
            return ['valid' => $len >= 1 && $len <= 3832, 'expected' => '1-3832 characters'];
        case 'MaxiCode':
            return ['valid' => $len >= 1 && $len <= 93, 'expected' => '1-93 characters'];
        case 'PDF417':
            return ['valid' => $len >= 1 && $len <= 2710, 'expected' => '1-2710 characters'];
        default:
            return ['valid' => true, 'expected' => 'any format'];
    }
}

function generateInternalBarcode(PDO $db, int $storeId, int $productId): string
{
    $prefix = 'WPS';
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    return sprintf('%s-%d-%d-%s', $prefix, $storeId, $productId, $random);
}

function generateBarcodeImagePath(string $barcode, string $type): string
{
    $dir = 'uploads/barcodes/';
    if (!is_dir(__DIR__ . '/' . $dir)) {
        @mkdir(__DIR__ . '/' . $dir, 0755, true);
    }
    $filename = $barcode . '_' . $type . '.svg';
    return $dir . $filename;
}

function productHasBarcode(PDO $db, int $productId): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM product_barcodes WHERE product_id = ?");
    $stmt->execute([$productId]);
    return (int) $stmt->fetchColumn() > 0;
}

function getProductPrimaryBarcode(PDO $db, int $productId, int $storeId): ?array
{
    $stmt = $db->prepare("SELECT * FROM product_barcodes WHERE product_id = ? AND store_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->execute([$productId, $storeId]);
    $barcode = $stmt->fetch();
    if ($barcode) return $barcode;
    $stmt = $db->prepare("SELECT * FROM product_barcodes WHERE product_id = ? AND store_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$productId, $storeId]);
    return $stmt->fetch() ?: null;
}

function getProductAllBarcodes(PDO $db, int $productId, ?int $storeId = null): array
{
    if ($storeId) {
        $stmt = $db->prepare("SELECT pb.*, u.full_name as created_by_name FROM product_barcodes pb LEFT JOIN users u ON u.id = pb.created_by WHERE pb.product_id = ? AND pb.store_id = ? ORDER BY pb.is_primary DESC, pb.id ASC");
        $stmt->execute([$productId, $storeId]);
    } else {
        $stmt = $db->prepare("SELECT pb.*, u.full_name as created_by_name FROM product_barcodes pb LEFT JOIN users u ON u.id = pb.created_by WHERE pb.product_id = ? ORDER BY pb.is_primary DESC, pb.id ASC");
        $stmt->execute([$productId]);
    }
    return $stmt->fetchAll();
}

function findProductByBarcode(PDO $db, string $barcode, int $storeId): ?array
{
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.barcode, p.price, p.stock_quantity, p.status, p.image, p.category,
               p.store_id, p.cost_price, p.low_stock_threshold,
               pb.barcode_type, pb.is_primary, pb.id as barcode_id
        FROM product_barcodes pb
        JOIN products p ON p.id = pb.product_id
        WHERE pb.barcode = ? AND pb.store_id = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$barcode, $storeId]);
    $product = $stmt->fetch();
    if ($product) return $product;

    $stmt = $db->prepare("
        SELECT id, name, barcode, price, stock_quantity, status, image, category,
               store_id, cost_price, low_stock_threshold,
               NULL as barcode_type, NULL as is_primary, NULL as barcode_id
        FROM products
        WHERE barcode = ? AND store_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$barcode, $storeId]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function logBarcodeAction(PDO $db, int $productId, ?int $barcodeId, string $action, ?string $oldBarcode, ?string $newBarcode, ?int $storeId, string $details = ''): void
{
    $stmt = $db->prepare("INSERT INTO barcode_audit_log (product_id, barcode_id, action, old_barcode, new_barcode, user_id, user_name, store_id, details) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $productId,
        $barcodeId,
        $action,
        $oldBarcode,
        $newBarcode,
        $_SESSION['user_id'] ?? 0,
        $_SESSION['user_name'] ?? '',
        $storeId ?? activeStoreId(),
        $details,
    ]);
}

function deleteHeldSale(PDO $db, int $id, int $userId, int $storeId): void
{
    try {
        $stmt = $db->prepare("DELETE FROM held_sales WHERE id = ? AND user_id = ? AND store_id = ?");
        $stmt->execute([$id, $userId, $storeId]);
    } catch (PDOException $e) {
        $stmt = $db->prepare("DELETE FROM held_sales WHERE id = ? AND cashier_id = ? AND store_id = ?");
        $stmt->execute([$id, $userId, $storeId]);
    }
}
