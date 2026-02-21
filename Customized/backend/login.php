<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['firebaseToken'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Firebase token is required']);
    exit();
}

$firebaseToken = $input['firebaseToken'];

try {
    // In production, verify Firebase token using Firebase Admin SDK
    // For development, we'll simulate verification
    $firebaseUID = 'test_firebase_uid_123'; // This would come from token verification

    $db = getDB();

    // Check if user exists
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE firebase_uid = ?");
    $stmt->execute([$firebaseUID]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists, return user data
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        // User doesn't exist, create new user
        // This would typically come from Firebase user data
        $userData = [
            'name' => $input['name'] ?? 'New User',
            'email' => $input['email'] ?? 'user@example.com',
            'firebase_uid' => $firebaseUID
        ];

        $stmt = $db->prepare("INSERT INTO users (name, email, firebase_uid) VALUES (?, ?, ?)");
        $stmt->execute([$userData['name'], $userData['email'], $userData['firebase_uid']]);

        $userId = $db->lastInsertId();

        // Create default settings for new user
        $stmt = $db->prepare("INSERT INTO settings (user_id, business_name, default_tax) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $userData['name'] . ' Business', 18.00]);

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $userData['name'],
                'email' => $userData['email']
            ],
            'isNewUser' => true
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Authentication failed: ' . $e->getMessage()]);
}
