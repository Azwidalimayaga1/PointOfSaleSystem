<?php

declare(strict_types=1);

/**
 * Small Firebase Authentication REST client. The Firebase Web API key is an
 * identifier, not a private credential; restrict it to this application's
 * domains in Google Cloud Console and keep it in the deployment environment.
 */
final class FirebaseAuth
{
    private const ENDPOINT = 'https://identitytoolkit.googleapis.com/v1/';

    public function __construct(private readonly string $apiKey)
    {
    }

    public static function fromEnvironment(): ?self
    {
        $apiKey = trim((string) (getenv('FIREBASE_WEB_API_KEY') ?: ''));
        return $apiKey !== '' ? new self($apiKey) : null;
    }

    public function signInWithPassword(string $email, string $password): array
    {
        return $this->request('accounts:signInWithPassword', [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]);
    }

    public function createUser(string $email, string $password): array
    {
        return $this->request('accounts:signUp', [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]);
    }

    public function sendPasswordResetEmail(string $email): void
    {
        $this->request('accounts:sendOobCode', [
            'requestType' => 'PASSWORD_RESET',
            'email' => $email,
        ]);
    }

    private function request(string $path, array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required for Firebase Authentication.');
        }

        $url = self::ENDPOINT . $path . '?key=' . rawurlencode($this->apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Firebase Authentication is temporarily unavailable: ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            error_log('Firebase Authentication request failed with HTTP ' . $status);
            throw new RuntimeException('Firebase Authentication request failed.');
        }

        return $data;
    }
}
