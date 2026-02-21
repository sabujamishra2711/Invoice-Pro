<?php
// Authentication Controller

class AuthController
{
    public function login($input)
    {
        // Accept email/password login (development mode)
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!$email) {
            return [
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Email is required',
                'http_code' => 400
            ];
        }

        try {
            $db = getDB();

            // Look up user by email
            $stmt = $db->prepare("SELECT id, name, email, firebase_uid FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // User exists — return token and user data
                // In production, verify password hash here
                $token = base64_encode($user['firebase_uid'] . ':' . time());

                return [
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'name' => $user['name'],
                        'user' => [
                            'id' => $user['id'],
                            'name' => $user['name'],
                            'email' => $user['email']
                        ]
                    ]
                ];
            } else {
                // Auto-create user in development mode
                $name = explode('@', $email)[0];
                $firebaseUID = 'dev_' . md5($email);

                $stmt = $db->prepare("INSERT INTO users (name, email, firebase_uid) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $firebaseUID]);
                $userId = $db->lastInsertId();

                // Create default settings
                $stmt = $db->prepare("INSERT INTO settings (user_id, business_name, default_tax) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $name . ' Business', 18.00]);

                $token = base64_encode($firebaseUID . ':' . time());

                return [
                    'success' => true,
                    'data' => [
                        'token' => $token,
                        'name' => $name,
                        'user' => [
                            'id' => $userId,
                            'name' => $name,
                            'email' => $email
                        ],
                        'isNewUser' => true
                    ]
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'AUTH_FAILED',
                'message' => 'Authentication failed: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }
}
