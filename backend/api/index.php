<?php
// Central API Entry Point

// Suppress PHP errors from appearing in JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS headers first
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '*';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit();
}

header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
header('Access-Control-Expose-Headers: Content-Disposition');
// Content-Type is set per-route; CSV export controllers set their own before exit()

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include core components
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/router.php';

// Conditionally include Logger if available
if (file_exists(__DIR__ . '/../services/Logger.php')) {
    require_once __DIR__ . '/../services/Logger.php';
    Logger::init();
}

// Conditionally include RateLimiter
if (file_exists(__DIR__ . '/../services/RateLimiter.php')) {
    require_once __DIR__ . '/../services/RateLimiter.php';
}

// CSRF protection helper (load but don't enforce on all routes)
if (file_exists(__DIR__ . '/../helpers/CSRFProtection.php')) {
    require_once __DIR__ . '/../helpers/CSRFProtection.php';
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $route = $_GET['route'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Auth routes don't require token or CSRF
        $publicRoutes = ['auth.login', 'auth.register', 'razorpay.pricing'];
    $isPublicRoute = in_array($route, $publicRoutes);

    // Authenticate for non-public routes
    $userId = null;
    if (!$isPublicRoute) {
        $userId = authenticateRequest();
    }

    // Store userId for controllers to use
    $GLOBALS['current_user_id'] = $userId;

    // Route the request
    $result = routeRequest($method, $route, $input);

    // Send response
    if (isset($result['success']) && $result['success']) {
        sendSuccess($result['data'] ?? [], $result['message'] ?? 'Success');
    } else {
        $errorCode = $result['error_code'] ?? 'UNKNOWN_ERROR';
        $message = $result['message'] ?? 'An error occurred';
        $httpCode = $result['http_code'] ?? 500;

        // Pass through validation details if present
        if ($errorCode === 'VALIDATION_ERROR' && isset($result['data']['errors'])) {
            sendValidationError($result['data']['errors']);
        } else {
            sendError($errorCode, $message, $httpCode);
        }
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    sendError('INTERNAL_ERROR', $e->getMessage(), 500);
}
