<?php

declare(strict_types=1);

function apiRateLimitCheck(PDO $db): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND success = 0");
    $stmt->execute([$ip]);
    $attempts = (int) $stmt->fetchColumn();
    return $attempts >= 10 ? 'Too many requests. Try again later.' : null;
}

switch ($method) {
    case 'POST':
        if ($id === 'login') {
            $rateError = apiRateLimitCheck($db);
            if ($rateError) {
                jsonResponse(['error' => $rateError], 429);
            }

            $input = getJsonInput();
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';

            if (!$email || !$password) {
                jsonResponse(['error' => 'Email and password required'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch();

            if (!$user) {
                $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at, success) VALUES (?, ?, NOW(), 0)");
                $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', $email]);
                jsonResponse(['error' => 'Invalid credentials'], 401);
            }

            $authenticated = false;
            if (!empty($user['firebase_uid'])) {
                if (!$firebase instanceof FirebaseAuth || empty($user['email'])) {
                    jsonResponse(['error' => 'Firebase Authentication is not configured for this account.'], 503);
                }
                try {
                    $result = $firebase->signInWithPassword($user['email'], $password);
                    $firebaseUid = (string) ($result['localId'] ?? '');
                    if ($firebaseUid !== '' && hash_equals((string) $user['firebase_uid'], $firebaseUid)) {
                        $authenticated = true;
                    }
                } catch (RuntimeException $e) {
                    error_log($e->getMessage());
                }
            } elseif (!empty($user['password'])) {
                // Temporary migration bridge for pre-Firebase accounts.
                $authenticated = verifyPassword($password, $user['password']);
            }

            if (!$authenticated) {
                $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at, success) VALUES (?, ?, NOW(), 0)");
                $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', $email]);
                jsonResponse(['error' => 'Invalid credentials'], 401);
            }

            if ($user['status'] !== 'active') {
                jsonResponse(['error' => $user['status'] === 'pending' ? 'Account pending approval' : 'Account deactivated'], 403);
            }

            $accessToken = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $db->prepare("INSERT INTO sessions (user_id, access_token, refresh_token, expires_at, ip_address, user_agent, store_id) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), ?, ?, ?)");
            $stmt->execute([$user['id'], $accessToken, $refreshToken, $ip, $ua, $user['store_id'] ? (int) $user['store_id'] : null]);

            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at, success) VALUES (?, ?, NOW(), 1)");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'unknown', $email]);

            logActivity($db, (int) $user['id'], $user['username'], 'login', 'Mobile app login');

            jsonResponse([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'user' => [
                    'id' => (int) $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                ]
            ]);
        }

        if ($id === 'register') {
            $input = getJsonInput();
            $username = $input['username'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            $fullName = $input['full_name'] ?? $username;
            $storeId = (int) ($input['store_id'] ?? 0);

            $ip = getClientIp();

            // Registration rate limit: max 3 per IP per hour
            $remaining = rate_limit_check_sliding($db, 'ip:' . $ip, 'register_api', 3, 3600);
            if ($remaining === 0) {
                jsonResponse(['error' => 'Too many registration attempts. Try again later.'], 429);
            }

            if (!$username || !$email || !$password) {
                jsonResponse(['error' => 'Username, email, and password required'], 400);
            }

            // Validate store
            $stmt = $db->prepare("SELECT id FROM stores WHERE id = ?");
            $stmt->execute([$storeId]);
            if (!$stmt->fetch()) {
                jsonResponse(['error' => 'Invalid store selected'], 400);
            }

            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                jsonResponse(['error' => 'Username or email already exists'], 409);
            }

            if (strlen($password) < 12) {
                jsonResponse(['error' => 'Password must be at least 12 characters'], 400);
            }

            // Domain-based registration limit
            $domain = substr(strrchr($email, '@'), 1);
            $remainingDomain = rate_limit_check_sliding($db, 'domain:' . $domain, 'register_domain_api', 10, 86400);
            if ($remainingDomain === 0) {
                jsonResponse(['error' => 'Too many registrations from this email domain.'], 429);
            }

            rate_limit_hit($db, 'ip:' . $ip, 'register_api');
            rate_limit_hit($db, 'domain:' . $domain, 'register_domain_api');

            if (!$firebase instanceof FirebaseAuth) {
                jsonResponse(['error' => 'Firebase Authentication is not configured.'], 503);
            }
            try {
                $result = $firebase->createUser($email, $password);
                $firebaseUid = (string) ($result['localId'] ?? '');
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
                jsonResponse(['error' => 'Registration failed.'], 400);
            }
            if ($firebaseUid === '') {
                jsonResponse(['error' => 'Registration failed.'], 400);
            }

            $hash = securePasswordHash($password);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role, status, firebase_uid, store_id) VALUES (?, ?, ?, ?, 'cashier', 'pending', ?, ?)");
            $stmt->execute([$username, $email, $hash, $fullName, $firebaseUid, $storeId]);

            $userId = (int) $db->lastInsertId();
            generateEmailVerificationToken($db, $userId);

            jsonResponse(['success' => true, 'message' => 'Registration successful. Check email to verify, then wait for admin approval.', 'user_id' => $userId, 'store_id' => $storeId], 201);
        }

        if ($id === 'refresh') {
            $input = getJsonInput();
            $refreshToken = $input['refresh_token'] ?? '';
            if (!$refreshToken) {
                jsonResponse(['error' => 'Refresh token required'], 400);
            }

            $stmt = $db->prepare("SELECT * FROM sessions WHERE refresh_token = ? AND expires_at > NOW()");
            $stmt->execute([$refreshToken]);
            $session = $stmt->fetch();

            if (!$session) {
                jsonResponse(['error' => 'Invalid or expired refresh token'], 401);
            }

            $newAccessToken = bin2hex(random_bytes(32));
            $newRefreshToken = bin2hex(random_bytes(32));
            $stmt = $db->prepare("UPDATE sessions SET access_token = ?, refresh_token = ? WHERE id = ?");
            $stmt->execute([$newAccessToken, $newRefreshToken, $session['id']]);

            jsonResponse([
                'token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
            ]);
        }

        if ($id === 'logout') {
            $input = getJsonInput();
            $token = $input['token'] ?? '';
            if ($token) {
                $stmt = $db->prepare("DELETE FROM sessions WHERE access_token = ?");
                $stmt->execute([$token]);
            }
            jsonResponse(['success' => true]);
        }

        if ($id === 'forgot-password') {
            $input = getJsonInput();
            $email = $input['email'] ?? '';
            if (!$email) {
                jsonResponse(['error' => 'Email required'], 400);
            }

            $ip = getClientIp();
            $remaining = rate_limit_check_sliding($db, 'ip:' . $ip, 'forgot_password_api', 3, 900);
            if ($remaining === 0) {
                jsonResponse(['error' => 'Too many password reset requests. Try again later.'], 429);
            }

            rate_limit_hit($db, 'ip:' . $ip, 'forgot_password_api');

            if ($firebase instanceof FirebaseAuth) {
                try { $firebase->sendPasswordResetEmail($email); } catch (RuntimeException $e) { error_log($e->getMessage()); }
            }
            logActivity($db, 0, 'system', 'password_reset_request', "Password reset requested for $email");
            jsonResponse(['message' => 'If the email exists, a reset link has been sent.']);
        }

        if ($id === 'reset-password') {
            $input = getJsonInput();
            $token = $input['token'] ?? '';
            $password = $input['password'] ?? '';

            if (!$token || !$password) {
                jsonResponse(['error' => 'Token and password required'], 400);
            }
            if (strlen($password) < 12) {
                jsonResponse(['error' => 'Password must be at least 12 characters'], 400);
            }

            $ip = getClientIp();
            $remaining = rate_limit_check_sliding($db, 'ip:' . $ip, 'reset_password_api', 5, 3600);
            if ($remaining === 0) {
                jsonResponse(['error' => 'Too many reset attempts. Try again later.'], 429);
            }
            rate_limit_hit($db, 'ip:' . $ip, 'reset_password_api');

            jsonResponse(['error' => 'Use the Firebase password-reset link sent to your email.'], 400);
        }

        jsonResponse(['error' => 'Not found'], 404);

    case 'GET':
        if ($id === 'me') {
            $user = requireAuth($db);
            jsonResponse(['user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ]]);
        }
        jsonResponse(['error' => 'Not found'], 404);

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
