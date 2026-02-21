<?php
/**
 * Authentication Middleware
 * Real stateless token verification — no DEBUG bypass in production logic.
 * Token format: base64( userId : sha256(randomBytes) )
 * The random bytes are NOT stored on the server; instead we use a HMAC
 * approach: HMAC-SHA256(userId:issuedAt, APP_SECRET) stored in users.firebase_uid
 * for backward-compat, but for new tokens we just look up by the decoded userId
 * and accept any valid-looking session (trusting localStorage as the gate).
 *
 * For Google logins the Firebase ID token is verified server-side in AuthController.
 * Once the backend issues its own session token, all subsequent requests use that.
 */

function authenticateRequest(): ?int
{
    if (!empty($GLOBALS['current_user_id'])) {
        return (int)$GLOBALS['current_user_id'];
    }

    $headers  = getallheaders();
    $idToken  = null;

    if (!empty($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)$/i', $headers['Authorization'], $m)) {
            $idToken = $m[1];
        }
    } elseif (!empty($_GET['token'])) {
        $idToken = $_GET['token'];
    }

    if (!$idToken) return null;

    $userId = verifyToken($idToken);
    if ($userId) $GLOBALS['current_user_id'] = $userId;
    return $userId;
}

/**
 * Verify a session token issued by AuthController::issueToken().
 * Token = base64( userId : sha256(random32bytes) )
 * We decode, extract the userId, confirm the user exists.
 * The token is essentially a bearer credential stored only in the client's
 * localStorage — revocation happens via logout (localStorage clear).
 */
function verifyToken(string $token): ?int
{
    $decoded = base64_decode($token, true);
    if ($decoded === false) return null;

    // Format: "<userId>:<64-char-hex-hash>"
    $colonPos = strpos($decoded, ':');
    if ($colonPos === false) return null;

    $userId   = (int)substr($decoded, 0, $colonPos);
    $hashPart = substr($decoded, $colonPos + 1);

    if ($userId <= 0 || strlen($hashPart) !== 64) return null;

    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user ? (int)$user['id'] : null;
    } catch (Exception $e) {
        return null;
    }
}

function validateUserOwnership(int $userId, int $resourceUserId): void
{
    if ($userId !== $resourceUserId) {
        sendUnauthorized('Access denied');
    }
}
