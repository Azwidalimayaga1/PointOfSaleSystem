<?php

declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Restrictive CORS — only allow known origins
$allowedOrigins = ['http://localhost:8000', 'http://localhost', 'http://127.0.0.1:8000', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: http://localhost:8000');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Store-ID');
header('Access-Control-Max-Age: 86400');
header('Vary: Origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Periodic cleanup
if (random_int(1, 100) === 1) {
    cleanupExpiredSessions($db);
    cleanupRateLimits($db);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// General API rate limiting: 60 req/min per IP for GET (scraping protection), 30 req/min for mutations
$ip = getClientIp();
$method = $_SERVER['REQUEST_METHOD'];
$isMutation = in_array($method, ['POST', 'PUT', 'DELETE'], true);
$limit = $isMutation ? 30 : 60;
$window = 60;

$remaining = rate_limit_check_sliding($db, 'ip:' . $ip, 'api_' . strtolower($method), $limit, $window);
if ($remaining === 0) {
    jsonResponse(['error' => 'Rate limit exceeded. Try again later.'], 429);
}
rate_limit_hit($db, 'ip:' . $ip, 'api_' . strtolower($method));

// Add rate-limit headers
header('X-RateLimit-Limit: ' . $limit);
header('X-RateLimit-Remaining: ' . $remaining);

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

$basePath = '/api/v1';
if (str_starts_with($uri, $basePath)) {
    $path = substr($uri, strlen($basePath));
} else {
    jsonResponse(['error' => 'Not found'], 404);
}

$path = '/' . trim($path, '/');
$parts = explode('/', trim($path, '/'));
$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;
$subResource = $parts[2] ?? null;

try {
    switch ($resource) {
        case 'auth':
            require __DIR__ . '/v1/auth.php';
            break;
        case 'products':
            require __DIR__ . '/v1/products.php';
            break;
        case 'sales':
            require __DIR__ . '/v1/sales.php';
            break;
        case 'customers':
            require __DIR__ . '/v1/customers.php';
            break;
        case 'inventory':
            require __DIR__ . '/v1/inventory.php';
            break;
        case 'returns':
            require __DIR__ . '/v1/returns.php';
            break;
        case 'reports':
            require __DIR__ . '/v1/reports.php';
            break;
        case 'settings':
            require __DIR__ . '/v1/settings.php';
            break;
        case 'users':
            require __DIR__ . '/v1/users.php';
            break;
        case 'messages':
            require __DIR__ . '/v1/messages.php';
            break;
        case 'dashboard':
            require __DIR__ . '/v1/dashboard.php';
            break;
        default:
            jsonResponse(['error' => 'Not found'], 404);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
