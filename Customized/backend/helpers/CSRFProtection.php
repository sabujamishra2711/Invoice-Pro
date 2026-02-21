<?php
// CSRF Protection Middleware

class CSRFProtection
{

    public static function generateToken()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateToken($token)
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        // For API requests, check header
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($headerToken && hash_equals($_SESSION['csrf_token'] ?? '', $headerToken)) {
            return true;
        }

        // For form submissions, check POST data
        if ($token && hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            return true;
        }

        return false;
    }

    public static function requireToken()
    {
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' ||
            $_SERVER['REQUEST_METHOD'] === 'PUT' ||
            $_SERVER['REQUEST_METHOD'] === 'DELETE'
        ) {

            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

            if (!self::validateToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'code' => 'CSRF_TOKEN_INVALID',
                        'message' => 'CSRF token validation failed'
                    ]
                ]);
                Logger::security('CSRF Token Validation Failed', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
                exit();
            }
        }
    }

    public static function getHeader()
    {
        return 'X-CSRF-Token: ' . self::generateToken();
    }
}
