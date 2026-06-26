<?php

declare(strict_types=1);

class SupabaseAuth
{
    private string $url;
    private string $anonKey;
    private string $serviceKey;

    public function __construct(string $url, string $anonKey, string $serviceKey = '')
    {
        $this->url = rtrim($url, '/');
        $this->anonKey = $anonKey;
        $this->serviceKey = $serviceKey;
    }

    private function request(string $method, string $path, array $headers = [], ?array $body = null, ?string $token = null): array
    {
        $url = $this->url . $path;
        $ch = curl_init($url);

        // Start with default headers; callers can override key:value pairs
        $h = [
            'Content-Type: application/json',
        ];
        $apiKey = $this->anonKey;
        foreach ($headers as $kv) {
            if (str_starts_with($kv, 'apikey:')) {
                $apiKey = trim(substr($kv, 7));
                continue;
            }
            $h[] = $kv;
        }
        array_unshift($h, 'apikey: ' . $apiKey);
        if ($token) {
            $h[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 15,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } elseif ($method === 'PUT' || $method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = $response ? json_decode($response, true) : [];

        if ($httpCode >= 400) {
            $msg = $data['error_description'] ?? $data['error'] ?? $data['msg'] ?? 'Supabase request failed';
            throw new RuntimeException($msg, $httpCode);
        }

        return $data;
    }

    // Public sign-in (uses anon key)
    public function signInWithPassword(string $email, string $password): array
    {
        return $this->request('POST', '/auth/v1/token?grant_type=password', [], [
            'email' => $email,
            'password' => $password,
        ]);
    }

    // Public sign-up (uses anon key; sends confirmation email)
    public function signUp(string $email, string $password): array
    {
        return $this->request('POST', '/auth/v1/signup', [], [
            'email' => $email,
            'password' => $password,
        ]);
    }

    // Sign out (invalidates refresh token)
    public function signOut(string $accessToken): void
    {
        $this->request('POST', '/auth/v1/logout', [], null, $accessToken);
    }

    // Get current user from access token
    public function getUser(string $accessToken): array
    {
        return $this->request('GET', '/auth/v1/user', [], null, $accessToken);
    }

    // Update password for the currently authenticated user
    public function updatePassword(string $accessToken, string $newPassword): array
    {
        return $this->request('PUT', '/auth/v1/user', [], [
            'password' => $newPassword,
        ], $accessToken);
    }

    // Refresh an expired access token
    public function refreshToken(string $refreshToken): array
    {
        return $this->request('POST', '/auth/v1/token?grant_type=refresh_token', [], [
            'refresh_token' => $refreshToken,
        ]);
    }

    // --- Admin operations (require service_role key) ---

    public function adminCreateUser(string $email, string $password, array $userMetadata = []): array
    {
        if (!$this->serviceKey) {
            throw new RuntimeException('SUPABASE_SERVICE_KEY is not configured');
        }
        return $this->request('POST', '/auth/v1/admin/users', [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
        ], [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
            'user_metadata' => $userMetadata,
        ]);
    }

    public function adminDeleteUser(string $uid): void
    {
        if (!$this->serviceKey) {
            throw new RuntimeException('SUPABASE_SERVICE_KEY is not configured');
        }
        $this->request('DELETE', '/auth/v1/admin/users/' . $uid, [
            'apikey: ' . $this->serviceKey,
            'Authorization: Bearer ' . $this->serviceKey,
        ]);
    }
}
