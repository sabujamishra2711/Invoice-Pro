<?php
// Authentication Middleware

define('DEBUG', true); // Development mode

function authenticateRequest()
{
    // If already authenticated by index.php, return the cached user ID
    if (!empty($GLOBALS['current_user_id'])) {
        return $GLOBALS['current_user_id'];
    }

    $headers = getallheaders();
    $idToken = null;

    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $idToken = $matches[1];
        }
    } elseif (isset($_GET['token'])) {
        $idToken = $_GET['token'];
    }

    if (!$idToken) {
        return null;
    }

    $idToken = $matches[1];

    // Verify token and get user ID
    return verifyToken($idToken);
}

function verifyToken($token)
{
    // Development mode — accept any token and return test user
    if (defined('DEBUG') && DEBUG) {
        $db = getDB();

        // Try to decode the token to get the firebase_uid
        $decoded = base64_decode($token);
        if ($decoded) {
            $parts = explode(':', $decoded);
            $uid = $parts[0] ?? null;

            if ($uid) {
                $stmt = $db->prepare("SELECT id FROM users WHERE firebase_uid = ?");
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
                if ($user) {
                    return $user['id'];
                }
            }
        }

        // Fallback: return first user (test user)
        $stmt = $db->prepare("SELECT id FROM users ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        return $user ? $user['id'] : null;
    }

    // Production: implement Firebase Admin SDK verification here
    return null;
}

function validateUserOwnership($userId, $resourceUserId)
{
    if ($userId !== $resourceUserId) {
        sendUnauthorized('Access denied');
    }
}
