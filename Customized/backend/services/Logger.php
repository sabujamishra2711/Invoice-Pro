<?php
// Logging Service - Centralized logging for all application events

class Logger
{
    private static $logFile = __DIR__ . '/../../storage/logs/app.log';
    private static $errorFile = __DIR__ . '/../../storage/logs/error.log';

    public static function init()
    {
        // Create log directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
        // Also log to error file
        self::logToFile(self::$errorFile, 'ERROR', $message, $context);
    }

    public static function security($message, $context = [])
    {
        self::log('SECURITY', $message, $context);
    }

    public static function payment($message, $context = [])
    {
        self::log('PAYMENT', $message, $context);
    }

    public static function auth($message, $context = [])
    {
        self::log('AUTH', $message, $context);
    }

    private static function log($level, $message, $context = [])
    {
        // Format log entry
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logEntry = "[$timestamp] $level: $message $contextStr" . PHP_EOL;

        // Log to file
        self::logToFile(self::$logFile, $level, $message, $context);

        // In development, also output to console
        if (defined('DEBUG') && DEBUG) {
            echo $logEntry;
        }
    }

    private static function logToFile($file, $level, $message, $context)
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logEntry = "[$timestamp] $level: $message $contextStr" . PHP_EOL;

        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }

    // Log API request details
    public static function logRequest($method, $route, $userId = null, $ip = null)
    {
        $context = [
            'method' => $method,
            'route' => $route,
            'user_id' => $userId,
            'ip' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        self::info('API Request', $context);
    }

    // Log authentication attempts
    public static function logAuthAttempt($success, $userId = null, $firebaseUID = null, $error = null)
    {
        $context = [
            'success' => $success,
            'user_id' => $userId,
            'firebase_uid' => $firebaseUID,
            'error' => $error,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        if ($success) {
            self::auth('Authentication Success', $context);
        } else {
            self::security('Authentication Failed', $context);
        }
    }

    // Log payment attempts
    public static function logPaymentAttempt($invoiceId, $amount, $userId, $success, $error = null)
    {
        $context = [
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'user_id' => $userId,
            'success' => $success,
            'error' => $error
        ];

        if ($success) {
            self::payment('Payment Success', $context);
        } else {
            self::error('Payment Failed', $context);
        }
    }

    // Log security violations
    public static function logSecurityViolation($violationType, $details = [])
    {
        $context = array_merge($details, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        self::security("Security Violation: $violationType", $context);
    }
}
