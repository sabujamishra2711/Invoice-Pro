<?php
define('RAZORPAY_KEY_ID',     'rzp_live_S0FczTC6hWZ8Nb');
define('RAZORPAY_KEY_SECRET', '5Hs0RA2jnEdngIlOYVQwbpDi');
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'invoice_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Firebase configuration — reads from env vars (Orchids secrets / Apache SetEnv / system env)
function _env(string $key, string $default = ''): string {
    return getenv($key) ?: ($_ENV[$key] ?? $_SERVER[$key] ?? $default);
}
define('FIREBASE_PROJECT_ID',  _env('FIREBASE_PROJECT_ID',  'your-firebase-project-id'));
define('FIREBASE_API_KEY',     _env('FIREBASE_API_KEY',     ''));
define('FIREBASE_AUTH_DOMAIN', _env('FIREBASE_AUTH_DOMAIN', ''));
define('FIREBASE_APP_ID',      _env('FIREBASE_APP_ID',      ''));

// Get the origin from the request for dynamic configuration
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'http://localhost');

// Application settings - dynamic based on request origin
if (!defined('APP_URL')) define('APP_URL', $origin);
if (!defined('API_URL')) define('API_URL', $origin . '/invoice-management/backend/api');

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
