<?php

declare(strict_types=1);

function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getAuthUser(PDO $db): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return null;
    }
    $token = $m[1];

    $stmt = $db->prepare("SELECT u.id, u.username, u.full_name, u.email, u.role, u.status, u.store_id, s.ip_address, s.user_agent, s.expires_at FROM users u JOIN sessions s ON s.user_id = u.id WHERE s.access_token = ?");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    // Check expiration
    if ($session['expires_at'] && strtotime($session['expires_at']) < time()) {
        $stmt = $db->prepare("DELETE FROM sessions WHERE access_token = ?");
        $stmt->execute([$token]);
        return null;
    }

    // Check IP binding (enforce when IP was stored)
    if ($session['ip_address']) {
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($currentIp && $session['ip_address'] !== $currentIp) {
            error_log('Session hijacking detected: token ' . substr($token, 0, 8) . '... used from IP ' . $currentIp . ' (bound to ' . $session['ip_address'] . ')');
            // Block the request — IP mismatch indicates stolen token
            $stmt = $db->prepare("DELETE FROM sessions WHERE access_token = ?");
            $stmt->execute([$token]);
            return null;
        }
    }

    if ($session['status'] !== 'active') {
        return null;
    }

    return [
        'id' => (int) $session['id'],
        'username' => $session['username'],
        'full_name' => $session['full_name'],
        'email' => $session['email'],
        'role' => $session['role'],
        'status' => $session['status'],
        'store_id' => $session['store_id'] ? (int) $session['store_id'] : null,
    ];
}

function requireAuth(PDO $db): array
{
    $user = getAuthUser($db);
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    if ($user['status'] !== 'active') {
        jsonResponse(['error' => 'Account is not active'], 403);
    }
    return $user;
}

function requireRole(array $user, string ...$roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
}

function getParam(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function generateReceiptNumber(PDO $db): string
{
    $stmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM sales");
    $nextId = $stmt->fetchColumn();
    return 'RCP-' . date('Ymd') . '-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
}
