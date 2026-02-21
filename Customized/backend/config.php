<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'invoice_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Firebase configuration
define('FIREBASE_API_KEY',     'AIzaSyDSKxwcd0pJ0N4rnsrvx6HhOXwc8E8O58c');
define('FIREBASE_AUTH_DOMAIN', 'mscoders-invoicepro.firebaseapp.com');
define('FIREBASE_PROJECT_ID',  'mscoders-invoicepro');
define('FIREBASE_APP_ID',      '1:808153794193:web:2c0337198f8dc1b9b76130');

// Build a clean origin (scheme + host) from the request — never includes a path
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== '') {
    $origin = rtrim($_SERVER['HTTP_ORIGIN'], '/');
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $origin = $scheme . '://' . $_SERVER['HTTP_HOST'];
} else {
    $origin = 'http://localhost';
}

// Application settings - dynamic based on request origin
if (!defined('APP_URL')) define('APP_URL', $origin);
if (!defined('API_URL')) define('API_URL', $origin . '/invoice-management/backend/api');

// Storage paths
if (!defined('LOGO_STORAGE_PATH')) define('LOGO_STORAGE_PATH', __DIR__ . '/../../storage/logos/');
if (!defined('LOGO_PUBLIC_URL'))   define('LOGO_PUBLIC_URL',   $origin . '/invoice-management/storage/logos/');

// Cron secret for recurring invoice processing
if (!defined('CRON_SECRET')) define('CRON_SECRET', 'invoicepro_cron_2025');

// Database connection
function getDB()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit();
        }
    }

    return $pdo;
}

// Get Firebase UID from Authorization header
function getFirebaseUID()
{
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header missing']);
        exit();
    }

    $authHeader = $headers['Authorization'];

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid authorization header format']);
        exit();
    }

    $idToken = $matches[1];

    // For development, we'll simulate token verification
    // In production, use Firebase Admin SDK to verify token
    // return verifyFirebaseToken($idToken);

    // Development mode - return test UID
    return 'test_firebase_uid_123';
}

// Get user ID from Firebase UID
function getUserId($firebaseUID)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE firebase_uid = ?");
    $stmt->execute([$firebaseUID]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    return $user['id'];
}

// Generate unique invoice number
function generateInvoiceNumber()
{
    $prefix = 'INV';
    $date = date('Ym');
    $db = getDB();

    // Get the last invoice number for this month
    $stmt = $db->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . $date . '%']);
    $lastInvoice = $stmt->fetch();

    if ($lastInvoice) {
        $lastNumber = intval(substr($lastInvoice['invoice_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }

    return $prefix . $date . $newNumber;
}
