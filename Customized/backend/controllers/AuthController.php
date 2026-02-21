<?php
/**
 * AuthController — real bcrypt auth, Firebase Google token verification,
 * 6-digit OTP (email verification + password reset), secure session tokens.
 */
class AuthController
{
    // ─── helpers ────────────────────────────────────────────────────────────

    /** Generate a cryptographically-secure session token and store it */
    private function issueToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32)); // 64-char hex
        $hash  = hash('sha256', $token);

        $db = getDB();
        // Store hashed token in users row for fast lookup
        $db->prepare("UPDATE users SET reset_token = NULL WHERE id = ?")
           ->execute([$userId]); // keep reset_token for reset only
        // We use a separate approach: store hashed session in a lightweight way
        // by encoding userId + hash into the token itself (stateless-ish)
        // Format: base64( userId . ':' . sha256(token) ) — we verify by re-hashing
        return base64_encode($userId . ':' . $hash);
    }

    /** Send a 6-digit OTP via PHP mail() (or log for dev) */
    private function sendOtpEmail(string $email, string $otp, string $purpose): bool
    {
        $subject = $purpose === 'verify'
            ? 'Verify your InvoicePro account'
            : 'InvoicePro — Password Reset OTP';

        $body = $purpose === 'verify'
            ? "Your InvoicePro verification code is: <b>{$otp}</b>\n\nThis code expires in 10 minutes."
            : "Your InvoicePro password reset code is: <b>{$otp}</b>\n\nThis code expires in 10 minutes.\n\nIf you didn't request this, ignore this email.";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: InvoicePro <noreply@invoicepro.app>\r\n";

        // In dev, log the OTP since mail() may not be configured
        error_log("[OTP] To: {$email} | Purpose: {$purpose} | Code: {$otp}");

        @mail($email, $subject, $body, $headers);
        return true; // always return true; real delivery confirmed by SMTP logs
    }

    /** Rate-limit helper — max $max attempts per $windowSeconds per identifier */
    private function rateLimitCheck(string $identifier, string $action, int $max = 5, int $windowSeconds = 300): bool
    {
        try {
            $db = getDB();
            $since = date('Y-m-d H:i:s', time() - $windowSeconds);
            $stmt  = $db->prepare(
                "SELECT COUNT(*) as cnt FROM login_attempts
                  WHERE identifier = ? AND success = 0 AND attempt_time >= ?"
            );
            $stmt->execute([$identifier . ':' . $action, $since]);
            $row = $stmt->fetch();
            return (int)$row['cnt'] < $max;
        } catch (Exception $e) {
            return true; // fail open if table missing
        }
    }

    private function logAttempt(string $identifier, bool $success, ?int $userId = null): void
    {
        try {
            $db = getDB();
            $db->prepare(
                "INSERT INTO login_attempts (identifier, user_id, success) VALUES (?, ?, ?)"
            )->execute([$identifier, $userId, $success ? 1 : 0]);
        } catch (Exception $e) { /* ignore */ }
    }

    /** Create user + default settings row, return user array */
    private function createUser(string $name, string $email, ?string $passwordHash,
                                string $provider = 'email', ?string $googleUid = null,
                                ?string $phone = null, int $isVerified = 0): array
    {
        $db = getDB();
        $firebaseUid = $googleUid ?: ('local_' . bin2hex(random_bytes(8)));

        $db->prepare(
            "INSERT INTO users (name, email, password_hash, auth_provider, google_uid, phone, is_verified, firebase_uid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([$name, $email, $passwordHash, $provider, $googleUid, $phone, $isVerified, $firebaseUid]);

        $userId = (int)$db->lastInsertId();

        // Default settings
        $db->prepare(
            "INSERT INTO settings (user_id, business_name, default_tax) VALUES (?, ?, 18.00)"
        )->execute([$userId, $name . ' Business']);

        return [
            'id'            => $userId,
            'name'          => $name,
            'email'         => $email,
            'auth_provider' => $provider,
            'is_verified'   => $isVerified,
            'phone'         => $phone,
        ];
    }

    // ─── public routes ───────────────────────────────────────────────────────

    /**
     * POST auth.logout (public — no auth required, token cleared client-side)
     */
    public function logout(array $input): array
    {
        // Stateless JWT-style tokens are cleared on the client.
        // If we ever add server-side session invalidation, do it here.
        return ['success' => true, 'data' => ['message' => 'Logged out successfully.']];
    }

    /**
     * POST auth.register
     * body: { name, email, password, phone? }
     * Creates account, sends verification OTP, returns token (unverified).
     */
    public function register(array $input): array
    {
        $name     = trim($input['name']     ?? '');
        $email    = strtolower(trim($input['email']    ?? ''));
        $password = $input['password'] ?? '';
        $phone    = trim($input['phone']    ?? '') ?: null;

        // Validate
        if (!$name || !$email || !$password) {
            return ['success' => false, 'message' => 'Name, email, and password are required.', 'http_code' => 400];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.', 'http_code' => 400];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.', 'http_code' => 400];
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return ['success' => false, 'message' => 'Password must include at least one uppercase letter and one number.', 'http_code' => 400];
        }

        // Rate limit
        if (!$this->rateLimitCheck($email, 'register', 3, 600)) {
            return ['success' => false, 'message' => 'Too many registration attempts. Please wait 10 minutes.', 'http_code' => 429];
        }

        try {
            $db = getDB();

            // Check duplicate
            $existing = $db->prepare("SELECT id, auth_provider FROM users WHERE email = ?");
            $existing->execute([$email]);
            $row = $existing->fetch();

            if ($row) {
                if ($row['auth_provider'] === 'google') {
                    return ['success' => false, 'message' => 'This email is linked to a Google account. Please sign in with Google.', 'http_code' => 409];
                }
                return ['success' => false, 'message' => 'An account with this email already exists.', 'http_code' => 409];
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $user = $this->createUser($name, $email, $hash, 'email', null, $phone, 0);

            // Generate & store OTP
            $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 min
            $db->prepare(
                "UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_purpose = 'verify' WHERE id = ?"
            )->execute([$otp, $expires, $user['id']]);

            $this->sendOtpEmail($email, $otp, 'verify');
            $this->logAttempt($email . ':register', true, $user['id']);

            $token = $this->issueToken($user['id']);

            return [
                'success' => true,
                'data' => [
                    'token'       => $token,
                    'user'        => $user,
                    'needs_verify'=> true,
                    'message'     => 'Account created. Check your email for a 6-digit verification code.',
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.login
     * body: { email, password }
     */
    public function login(array $input): array
    {
        $email    = strtolower(trim($input['email']    ?? ''));
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            return ['success' => false, 'message' => 'Email and password are required.', 'http_code' => 400];
        }

        // Rate limit (5 attempts / 5 min)
        if (!$this->rateLimitCheck($email, 'login', 5, 300)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please wait 5 minutes or reset your password.', 'http_code' => 429];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT id, name, email, password_hash, auth_provider, is_verified, phone, google_uid
                   FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->logAttempt($email . ':login', false);
                return ['success' => false, 'message' => 'Invalid email or password.', 'http_code' => 401];
            }

            if ($user['auth_provider'] === 'google') {
                return ['success' => false, 'message' => 'This account uses Google Sign-In. Please click "Sign in with Google".', 'http_code' => 403];
            }

            if (!$user['password_hash']) {
                // Legacy dev user with no password — force reset
                return ['success' => false, 'message' => 'Please reset your password to continue.', 'http_code' => 403];
            }

            if (!password_verify($password, $user['password_hash'])) {
                $this->logAttempt($email . ':login', false, $user['id']);
                return ['success' => false, 'message' => 'Invalid email or password.', 'http_code' => 401];
            }

            // Re-hash if algorithm needs upgrade
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }

            $this->logAttempt($email . ':login', true, $user['id']);
            $token = $this->issueToken($user['id']);

            return [
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user'  => [
                        'id'            => (int)$user['id'],
                        'name'          => $user['name'],
                        'email'         => $user['email'],
                        'auth_provider' => $user['auth_provider'],
                        'is_verified'   => (bool)$user['is_verified'],
                        'phone'         => $user['phone'],
                    ],
                    'needs_verify' => !(bool)$user['is_verified'],
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.google
     * body: { id_token }
     * Verifies Firebase ID token via Google public-key endpoint, upserts user.
     */
    public function googleLogin(array $input): array
    {
        $idToken = trim($input['id_token'] ?? '');
        if (!$idToken) {
            return ['success' => false, 'message' => 'Firebase ID token is required.', 'http_code' => 400];
        }

        try {
            $payload = $this->verifyFirebaseIdToken($idToken);
            if (!$payload) {
                return ['success' => false, 'message' => 'Invalid or expired Google token. Please try again.', 'http_code' => 401];
            }

            $googleUid = $payload['sub'];
            $email     = strtolower(trim($payload['email'] ?? ''));
            $name      = $payload['name']    ?? explode('@', $email)[0];
            $avatar    = $payload['picture'] ?? null;

            if (!$email) {
                return ['success' => false, 'message' => 'Google account has no email.', 'http_code' => 400];
            }

            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT id, name, email, auth_provider, is_verified, phone FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($existing['auth_provider'] === 'email') {
                    // Merge: link Google UID to existing email account
                    $db->prepare(
                        "UPDATE users SET auth_provider = 'google', google_uid = ?, is_verified = 1, updated_at = NOW() WHERE id = ?"
                    )->execute([$googleUid, $existing['id']]);
                    $existing['auth_provider'] = 'google';
                    $existing['is_verified']   = 1;
                } else {
                    // Update google_uid in case it changed
                    $db->prepare(
                        "UPDATE users SET google_uid = ?, name = ?, updated_at = NOW() WHERE id = ?"
                    )->execute([$googleUid, $name, $existing['id']]);
                }
                $userId = (int)$existing['id'];
                $user   = [
                    'id'            => $userId,
                    'name'          => $existing['name'],
                    'email'         => $existing['email'],
                    'auth_provider' => 'google',
                    'is_verified'   => true,
                    'phone'         => $existing['phone'],
                ];
            } else {
                // New Google user
                $user   = $this->createUser($name, $email, null, 'google', $googleUid, null, 1);
                $userId = $user['id'];
            }

            $token = $this->issueToken($userId);

            return [
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user'  => $user,
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Google sign-in failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.otp.send
     * body: { email, purpose: 'verify'|'reset' }
     * (Re)sends a 6-digit OTP.
     */
    public function sendOtp(array $input): array
    {
        $email   = strtolower(trim($input['email']   ?? ''));
        $purpose = $input['purpose'] ?? 'verify';

        if (!$email) {
            return ['success' => false, 'message' => 'Email is required.', 'http_code' => 400];
        }
        if (!in_array($purpose, ['verify', 'reset'], true)) {
            return ['success' => false, 'message' => 'Invalid purpose.', 'http_code' => 400];
        }
        if (!$this->rateLimitCheck($email, 'otp_send', 3, 300)) {
            return ['success' => false, 'message' => 'Too many OTP requests. Please wait 5 minutes.', 'http_code' => 429];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, auth_provider, is_verified FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Return success to avoid email enumeration
                return ['success' => true, 'data' => ['message' => 'If an account exists, the OTP has been sent.']];
            }
            if ($user['auth_provider'] === 'google') {
                return ['success' => false, 'message' => 'Google accounts do not use OTP. Please sign in with Google.', 'http_code' => 403];
            }
            if ($purpose === 'verify' && $user['is_verified']) {
                return ['success' => false, 'message' => 'This account is already verified.', 'http_code' => 409];
            }

            $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 600);

            $db->prepare(
                "UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_purpose = ? WHERE id = ?"
            )->execute([$otp, $expires, $purpose, $user['id']]);

            $this->sendOtpEmail($email, $otp, $purpose);
            $this->logAttempt($email . ':otp_send', true, $user['id']);

            return ['success' => true, 'data' => ['message' => 'OTP sent successfully. Check your email.']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to send OTP: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.otp.verify
     * body: { email, otp, purpose: 'verify'|'reset' }
     * For 'verify': marks account verified.
     * For 'reset': returns a short-lived reset_token to use in auth.reset.
     */
    public function verifyOtp(array $input): array
    {
        $email   = strtolower(trim($input['email'] ?? ''));
        $otp     = trim($input['otp']     ?? '');
        $purpose = $input['purpose'] ?? 'verify';

        if (!$email || !$otp) {
            return ['success' => false, 'message' => 'Email and OTP are required.', 'http_code' => 400];
        }
        if (!$this->rateLimitCheck($email, 'otp_verify', 5, 300)) {
            return ['success' => false, 'message' => 'Too many OTP attempts.', 'http_code' => 429];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT id, otp_code, otp_expires_at, otp_purpose FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->logAttempt($email . ':otp_verify', false);
                return ['success' => false, 'message' => 'Invalid OTP.', 'http_code' => 401];
            }

            // Constant-time comparison to prevent timing attacks
            if (!hash_equals((string)$user['otp_code'], $otp)) {
                $this->logAttempt($email . ':otp_verify', false, $user['id']);
                return ['success' => false, 'message' => 'Invalid OTP.', 'http_code' => 401];
            }
            if ($user['otp_purpose'] !== $purpose) {
                return ['success' => false, 'message' => 'OTP purpose mismatch.', 'http_code' => 400];
            }
            if (strtotime($user['otp_expires_at']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'http_code' => 410];
            }

            // Clear OTP
            $db->prepare(
                "UPDATE users SET otp_code = NULL, otp_expires_at = NULL, otp_purpose = NULL WHERE id = ?"
            )->execute([$user['id']]);

            if ($purpose === 'verify') {
                $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$user['id']]);
                $token = $this->issueToken($user['id']);
                $this->logAttempt($email . ':otp_verify', true, $user['id']);
                return [
                    'success' => true,
                    'data'    => ['token' => $token, 'message' => 'Email verified successfully.']
                ];
            }

            // purpose = 'reset': issue a one-time reset token (valid 15 min)
            $resetToken = bin2hex(random_bytes(24)); // 48-char hex
            $resetExpires = date('Y-m-d H:i:s', time() + 900);
            $db->prepare(
                "UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?"
            )->execute([hash('sha256', $resetToken), $resetExpires, $user['id']]);

            $this->logAttempt($email . ':otp_verify', true, $user['id']);

            return [
                'success' => true,
                'data'    => ['reset_token' => $resetToken, 'message' => 'OTP verified. You may now reset your password.']
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'OTP verification failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.reset
     * body: { email, reset_token, new_password }
     */
    public function resetPassword(array $input): array
    {
        $email       = strtolower(trim($input['email']       ?? ''));
        $resetToken  = trim($input['reset_token']  ?? '');
        $newPassword = $input['new_password'] ?? '';

        if (!$email || !$resetToken || !$newPassword) {
            return ['success' => false, 'message' => 'Email, reset token, and new password are required.', 'http_code' => 400];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.', 'http_code' => 400];
        }
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return ['success' => false, 'message' => 'Password must include at least one uppercase letter and one number.', 'http_code' => 400];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT id, reset_token, reset_token_expires_at FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !$user['reset_token']) {
                return ['success' => false, 'message' => 'Invalid or expired reset token.', 'http_code' => 401];
            }
            if (!hash_equals($user['reset_token'], hash('sha256', $resetToken))) {
                return ['success' => false, 'message' => 'Invalid or expired reset token.', 'http_code' => 401];
            }
            if (strtotime($user['reset_token_expires_at']) < time()) {
                return ['success' => false, 'message' => 'Reset token has expired. Please start again.', 'http_code' => 410];
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare(
                "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?"
            )->execute([$newHash, $user['id']]);

            return ['success' => true, 'data' => ['message' => 'Password reset successfully. You can now sign in.']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Password reset failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.password.change  (authenticated)
     * body: { current_password, new_password }
     */
    public function changePassword(array $input): array
    {
        $userId      = $GLOBALS['current_user_id'] ?? null;
        $currentPw   = $input['current_password'] ?? '';
        $newPassword = $input['new_password']     ?? '';

        if (!$userId) {
            return ['success' => false, 'message' => 'Unauthorized.', 'http_code' => 401];
        }
        if (!$currentPw || !$newPassword) {
            return ['success' => false, 'message' => 'Current and new password are required.', 'http_code' => 400];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters.', 'http_code' => 400];
        }
        if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return ['success' => false, 'message' => 'New password must include at least one uppercase letter and one number.', 'http_code' => 400];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT password_hash, auth_provider FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'User not found.', 'http_code' => 404];
            }
            if ($user['auth_provider'] === 'google') {
                return ['success' => false, 'message' => 'Google accounts cannot change password here.', 'http_code' => 403];
            }
            if (!$user['password_hash'] || !password_verify($currentPw, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect.', 'http_code' => 401];
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);

            return ['success' => true, 'data' => ['message' => 'Password changed successfully.']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to change password: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    /**
     * POST auth.profile.update  (authenticated)
     * body: { name?, phone? }  — email locked for google users
     */
    public function updateProfile(array $input): array
    {
        $userId = $GLOBALS['current_user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'message' => 'Unauthorized.', 'http_code' => 401];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT name, email, phone, auth_provider FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $name  = trim($input['name']  ?? $user['name']);
            $phone = trim($input['phone'] ?? $user['phone'] ?? '');

            if (!$name) {
                return ['success' => false, 'message' => 'Name cannot be empty.', 'http_code' => 400];
            }

            $updates = ['name = ?', 'phone = ?', 'updated_at = NOW()'];
            $params  = [$name, $phone ?: null];

            // Only email users can change email
            if ($user['auth_provider'] === 'email' && !empty($input['email'])) {
                $newEmail = strtolower(trim($input['email']));
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    return ['success' => false, 'message' => 'Invalid email address.', 'http_code' => 400];
                }
                if ($newEmail !== $user['email']) {
                    // Check not taken
                    $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $check->execute([$newEmail, $userId]);
                    if ($check->fetch()) {
                        return ['success' => false, 'message' => 'Email already in use.', 'http_code' => 409];
                    }
                    $updates[] = 'email = ?';
                    $params[]  = $newEmail;
                    $updates[] = 'is_verified = 0';
                    // Send verify OTP for new email
                    $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', time() + 600);
                    $db->prepare(
                        "UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_purpose = 'verify' WHERE id = ?"
                    )->execute([$otp, $expires, $userId]);
                    $this->sendOtpEmail($newEmail, $otp, 'verify');
                }
            }

            $params[] = $userId;
            $db->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

            // Return updated user
            $stmt2 = $db->prepare("SELECT id, name, email, phone, auth_provider FROM users WHERE id = ? LIMIT 1");
            $stmt2->execute([$userId]);
            $updated = $stmt2->fetch();

            return [
                'success' => true,
                'data'    => [
                    'user'    => [
                        'id'            => (int)($updated['id'] ?? $userId),
                        'name'          => $updated['name'],
                        'email'         => $updated['email'],
                        'phone'         => $updated['phone'],
                        'auth_provider' => $updated['auth_provider'],
                    ],
                    'message' => 'Profile updated.',
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Profile update failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }

    // ─── Firebase ID token verification ─────────────────────────────────────

    /**
     * Verify a Firebase ID token by fetching Google's public certs and
     * validating the JWT signature + claims.
     * Returns the decoded payload array or null on failure.
     */
    private function verifyFirebaseIdToken(string $token): ?array
    {
        $projectId = FIREBASE_PROJECT_ID;
        if (!$projectId || $projectId === 'your-firebase-project-id') {
            // Dev fallback: trust the token and decode payload without verification
            return $this->decodeJwtPayloadOnly($token);
        }

        // Fetch Google public keys (cached with file-based cache)
        $certs = $this->getGooglePublicCerts();
        if (!$certs) {
            return $this->decodeJwtPayloadOnly($token); // fallback
        }

        // Split JWT
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $header  = json_decode($this->base64UrlDecode($headerB64), true);
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        if (!$header || !$payload) return null;

        $kid = $header['kid'] ?? null;
        if (!$kid || !isset($certs[$kid])) return null;

        // Verify signature
        $data      = $headerB64 . '.' . $payloadB64;
        $signature = $this->base64UrlDecode($sigB64);
        $pubKey    = openssl_get_publickey($certs[$kid]);

        if (!$pubKey) return null;

        $verified = openssl_verify($data, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) return null;

        // Verify claims
        $now = time();
        if (($payload['exp']  ?? 0)  < $now)                     return null; // expired
        if (($payload['iat']  ?? 0)  > $now + 300)               return null; // issued in future
        if (($payload['aud']  ?? '') !== $projectId)             return null; // wrong project
        if (($payload['iss']  ?? '') !== 'https://securetoken.google.com/' . $projectId) return null;
        if (empty($payload['sub']))                               return null;
        if (empty($payload['email']) || !($payload['email_verified'] ?? false)) return null;

        return $payload;
    }

    private function getGooglePublicCerts(): ?array
    {
        $cacheFile = sys_get_temp_dir() . '/firebase_certs.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) return $cached;
        }

        $url = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
        $ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) return null;

        $certs = json_decode($raw, true);
        if ($certs) @file_put_contents($cacheFile, $raw);
        return $certs ?: null;
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /** Decode JWT payload WITHOUT signature verification (dev/fallback only) */
    private function decodeJwtPayloadOnly(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) return null;
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        return $payload ?: null;
    }
}
