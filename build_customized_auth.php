<?php
/**
 * Writes all Customized auth/user-management files in one pass.
 * Run: C:\xampp\php\php.exe C:\xampp\htdocs\invoice-management\build_customized_auth.php
 */

$base = 'C:/xampp/htdocs/Customized/';
$log  = [];

function w(string $path, string $content): void {
    global $log;
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
    if (file_put_contents($path, $content) !== false)
        $log[] = "OK   " . str_replace('C:/xampp/htdocs/Customized/', '', $path);
    else
        $log[] = "FAIL " . str_replace('C:/xampp/htdocs/Customized/', '', $path);
}

// ═══════════════════════════════════════════════════════════════
// 1. login.html  — strip signup tab, point setup link below form
// ═══════════════════════════════════════════════════════════════
$loginHtml = file_get_contents($base . 'frontend/login.html');

// Remove the "Create Account" tab button
$loginHtml = str_replace(
    '<button class="auth-tab" id="tab-signup" onclick="switchTab(\'signup\')">Create Account</button>',
    '',
    $loginHtml
);

// Remove the entire sign-up form div
$loginHtml = preg_replace(
    '/<!-- ── SIGN UP FORM ──.*?<!-- \/.login-card -->/s',
    '<!-- signup removed -->

            </div><!-- /.login-card -->',
    $loginHtml
);

// Replace the Google sign-in redirect to /Customized/
$loginHtml = str_replace(
    "window.location.href = '/invoice-management/';",
    "window.location.href = '/Customized/';",
    $loginHtml
);
$loginHtml = str_replace(
    "window.location.href = '/invoice-management/';",
    "window.location.href = '/Customized/';",
    $loginHtml
);

// Fix firebase-config.php path
$loginHtml = str_replace(
    'src="/invoice-management/backend/api/firebase-config.php"',
    'src="/Customized/backend/api/firebase-config.php"',
    $loginHtml
);

// Update title and brand name
$loginHtml = str_replace('Sign In — InvoicePro', 'Sign In', $loginHtml);
$loginHtml = str_replace('>InvoicePro<', '>Business Suite<', $loginHtml);

w($base . 'frontend/login.html', $loginHtml);

// ═══════════════════════════════════════════════════════════════
// 2. setup.html  — one-time owner registration page
// ═══════════════════════════════════════════════════════════════
$setupHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Setup</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f0b2e 0%, #1a1150 40%, #2d1b69 100%); }
        .bg-anim { position: fixed; inset: 0; z-index: 0; }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.3; }
        .orb:nth-child(1) { width: 500px; height: 500px; background: radial-gradient(circle, #6366f1, transparent 70%); top: -10%; left: -5%; }
        .orb:nth-child(2) { width: 400px; height: 400px; background: radial-gradient(circle, #8b5cf6, transparent 70%); bottom: -15%; right: -10%; }
        .card { position: relative; z-index: 1; width: 100%; max-width: 440px; margin: 24px; background: rgba(255,255,255,0.06); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 40px 36px; box-shadow: 0 25px 60px rgba(0,0,0,0.35); }
        .badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); border-radius: 20px; padding: 6px 14px; font-size: 0.78rem; font-weight: 600; color: #a5b4fc; margin-bottom: 20px; }
        h1 { font-size: 1.6rem; font-weight: 800; color: #fff; margin-bottom: 6px; letter-spacing: -0.02em; }
        .sub { font-size: 0.87rem; color: rgba(255,255,255,0.45); margin-bottom: 28px; line-height: 1.5; }
        .input-group { margin-bottom: 16px; }
        .input-group label { display: block; font-size: 0.79rem; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 7px; letter-spacing: 0.02em; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.3); font-size: 0.87rem; pointer-events: none; }
        input { width: 100%; padding: 12px 44px 12px 40px; font-size: 0.88rem; font-family: inherit; color: #fff; background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.1); border-radius: 12px; outline: none; transition: all 0.2s; }
        input::placeholder { color: rgba(255,255,255,0.22); }
        input:focus { border-color: #6366f1; background: rgba(99,102,241,0.08); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        .toggle-password { position: absolute; right: 0; top: 50%; transform: translateY(-50%); width: 44px; height: 100%; background: none; border: none; color: rgba(255,255,255,0.3); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: color 0.2s; }
        .toggle-password:hover { color: rgba(255,255,255,0.6); }
        .pw-strength { margin-top: 6px; }
        .pw-bar { height: 3px; border-radius: 2px; background: rgba(255,255,255,0.1); margin-bottom: 4px; overflow: hidden; }
        .pw-fill { height: 100%; border-radius: 2px; width: 0; transition: width 0.3s, background 0.3s; }
        .pw-text { font-size: 0.73rem; color: rgba(255,255,255,0.4); }
        .btn { width: 100%; padding: 13px; font-size: 0.93rem; font-weight: 700; font-family: inherit; color: #fff; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; border-radius: 12px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 16px rgba(99,102,241,0.35); margin-top: 8px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(99,102,241,0.45); }
        .btn:disabled { opacity: 0.7; pointer-events: none; }
        .spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.25); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; display: none; }
        .btn.loading .btn-label { display: none; }
        .btn.loading .spinner { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .alert { display: none; margin-top: 14px; padding: 11px 14px; border-radius: 11px; font-size: 0.83rem; font-weight: 500; align-items: center; gap: 8px; }
        .alert.show { display: flex; }
        .alert-error { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }
        .alert-success { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.2); color: #6ee7b7; }
        .invalid-state { text-align: center; padding: 40px 0; }
        .invalid-state i { font-size: 3rem; color: #ef4444; margin-bottom: 16px; display: block; }
        .invalid-state h2 { color: #fff; margin-bottom: 8px; }
        .invalid-state p { color: rgba(255,255,255,0.45); font-size: 0.87rem; line-height: 1.6; }
        .success-state { text-align: center; padding: 20px 0; }
        .success-state i { font-size: 3rem; color: #10b981; margin-bottom: 16px; display: block; }
        .success-state h2 { color: #fff; margin-bottom: 8px; }
        .success-state p { color: rgba(255,255,255,0.45); font-size: 0.87rem; line-height: 1.6; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="bg-anim"><div class="orb"></div><div class="orb"></div></div>

    <div class="card" id="main-card">
        <!-- Content injected by JS based on token validity -->
        <div style="text-align:center;color:rgba(255,255,255,0.4);padding:40px 0;">
            <i class="fas fa-circle-notch fa-spin" style="font-size:2rem;"></i>
            <p style="margin-top:12px;font-size:0.87rem;">Validating setup link...</p>
        </div>
    </div>

    <script src="frontend/js/config.js"></script>
    <script>
    const token = new URLSearchParams(location.search).get('token');
    const card  = document.getElementById('main-card');

    function setLoading(yes) {
        const btn = document.getElementById('setup-btn');
        if (!btn) return;
        btn.classList.toggle('loading', yes);
        btn.disabled = yes;
    }

    function showAlert(msg, type = 'error') {
        const el = document.getElementById('setup-alert');
        if (!el) return;
        el.className = 'alert alert-' + type + ' show';
        el.querySelector('span').textContent = msg;
    }

    function togglePw(id, btn) {
        const inp = document.getElementById(id);
        inp.type = inp.type === 'password' ? 'text' : 'password';
        btn.querySelector('i').className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function updateStrength(pw) {
        const fill = document.getElementById('pw-fill');
        const text = document.getElementById('pw-text');
        let score = 0;
        if (pw.length >= 8)          score++;
        if (/[A-Z]/.test(pw))        score++;
        if (/[0-9]/.test(pw))        score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        const colors = ['#ef4444','#f59e0b','#10b981','#6366f1'];
        const labels = ['Weak','Fair','Good','Strong'];
        fill.style.width      = (score * 25) + '%';
        fill.style.background = colors[score - 1] || '#ef4444';
        text.textContent      = pw.length ? (labels[score - 1] || 'Weak') : 'Enter a password';
    }

    async function renderForm(clientName) {
        card.innerHTML = `
            <div class="badge"><i class="fas fa-shield-halved"></i> One-time Setup</div>
            <h1>Create your account</h1>
            <p class="sub">You're setting up the owner account for <strong style="color:#a5b4fc">${clientName}</strong>. This link expires after use.</p>

            <div class="input-group">
                <label>Full Name</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="s-name" placeholder="Your full name" required>
                </div>
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="s-email" placeholder="owner@company.com" required>
                </div>
            </div>
            <div class="input-group">
                <label>Phone <span style="color:rgba(255,255,255,0.3);font-weight:400;">(optional)</span></label>
                <div class="input-wrapper">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="tel" id="s-phone" placeholder="+91 98765 43210">
                </div>
            </div>
            <div class="input-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="s-pw" placeholder="Min 8 chars, 1 uppercase, 1 number" oninput="updateStrength(this.value)" required>
                    <button type="button" class="toggle-password" onclick="togglePw('s-pw',this)"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pw-strength">
                    <div class="pw-bar"><div class="pw-fill" id="pw-fill"></div></div>
                    <div class="pw-text" id="pw-text">Enter a password</div>
                </div>
            </div>
            <div class="input-group">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="s-pw2" placeholder="Repeat password" required>
                </div>
            </div>

            <button class="btn" id="setup-btn" onclick="submitSetup()">
                <div class="spinner"></div>
                <span class="btn-label"><i class="fas fa-user-check"></i> Create Account</span>
            </button>
            <div class="alert alert-error" id="setup-alert"><i class="fas fa-exclamation-circle"></i><span></span></div>
        `;
    }

    async function submitSetup() {
        const name = document.getElementById('s-name').value.trim();
        const email = document.getElementById('s-email').value.trim();
        const phone = document.getElementById('s-phone').value.trim();
        const pw    = document.getElementById('s-pw').value;
        const pw2   = document.getElementById('s-pw2').value;

        if (!name || !email || !pw) { showAlert('Name, email and password are required.'); return; }
        if (pw !== pw2)             { showAlert('Passwords do not match.'); return; }
        if (pw.length < 8)          { showAlert('Password must be at least 8 characters.'); return; }
        if (!/[A-Z]/.test(pw) || !/[0-9]/.test(pw)) {
            showAlert('Password must include at least one uppercase letter and one number.'); return;
        }

        setLoading(true);
        try {
            const res = await fetch(API_BASE + '?route=setup.complete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token, name, email, phone, password: pw })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Setup failed.');

            card.innerHTML = `
                <div class="success-state">
                    <i class="fas fa-circle-check"></i>
                    <h2>Account Created!</h2>
                    <p>Your owner account has been set up successfully. This setup link is now permanently disabled.</p>
                    <a href="frontend/login.html" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border-radius:12px;font-weight:700;text-decoration:none;font-size:0.9rem;">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            `;
        } catch(err) {
            showAlert(err.message);
            setLoading(false);
        }
    }

    // Validate token on load
    (async function() {
        if (!token) {
            card.innerHTML = `<div class="invalid-state"><i class="fas fa-ban"></i><h2>No setup token</h2><p>This page requires a valid setup link. Contact your system administrator.</p></div>`;
            return;
        }
        try {
            const res  = await fetch(API_BASE + '?route=setup.validate&token=' + encodeURIComponent(token));
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Invalid token');
            await renderForm(data.data.client_name);
        } catch(err) {
            card.innerHTML = `<div class="invalid-state"><i class="fas fa-link-slash"></i><h2>Link invalid or expired</h2><p>${err.message}</p></div>`;
        }
    })();
    </script>
</body>
</html>
HTML;

w($base . 'frontend/setup.html', $setupHtml);

// ═══════════════════════════════════════════════════════════════
// 3. SetupController.php
// ═══════════════════════════════════════════════════════════════
$setupController = <<<'PHP'
<?php
/**
 * SetupController — handles one-time owner account creation via a setup token.
 * The token is generated by the developer CLI (generate_setup_link.php)
 * and stored in the setup_tokens table. It can only be used once.
 */
class SetupController
{
    /**
     * GET setup.validate?token=xxx
     * Returns client_name if token is valid and unused.
     */
    public function validate(array $input): array
    {
        $token = trim($input['token'] ?? '');
        if (!$token) {
            return ['success' => false, 'message' => 'Token is required.', 'http_code' => 400];
        }

        $db   = getDB();
        $hash = hash('sha256', $token);
        $stmt = $db->prepare(
            "SELECT id, client_name, expires_at, used_at FROM setup_tokens WHERE token_hash = ? LIMIT 1"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => 'Invalid setup link.', 'http_code' => 404];
        }
        if ($row['used_at'] !== null) {
            return ['success' => false, 'message' => 'This setup link has already been used.', 'http_code' => 410];
        }
        if (strtotime($row['expires_at']) < time()) {
            return ['success' => false, 'message' => 'This setup link has expired. Ask your administrator for a new one.', 'http_code' => 410];
        }

        return [
            'success' => true,
            'data'    => ['client_name' => $row['client_name']],
        ];
    }

    /**
     * POST setup.complete
     * body: { token, name, email, phone?, password }
     * Creates the owner user, marks token as used.
     */
    public function complete(array $input): array
    {
        $token    = trim($input['token']    ?? '');
        $name     = trim($input['name']     ?? '');
        $email    = strtolower(trim($input['email']    ?? ''));
        $phone    = trim($input['phone']    ?? '') ?: null;
        $password = $input['password'] ?? '';

        // Basic validation
        if (!$token || !$name || !$email || !$password) {
            return ['success' => false, 'message' => 'All fields are required.', 'http_code' => 400];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.', 'http_code' => 400];
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters with one uppercase and one number.', 'http_code' => 400];
        }

        $db   = getDB();
        $hash = hash('sha256', $token);

        // Re-validate token (race condition safe)
        $stmt = $db->prepare(
            "SELECT id, expires_at, used_at FROM setup_tokens WHERE token_hash = ? LIMIT 1"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => 'Invalid setup link.', 'http_code' => 404];
        }
        if ($row['used_at'] !== null) {
            return ['success' => false, 'message' => 'This setup link has already been used.', 'http_code' => 410];
        }
        if (strtotime($row['expires_at']) < time()) {
            return ['success' => false, 'message' => 'This setup link has expired.', 'http_code' => 410];
        }

        // Check email not already taken
        $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'An account with this email already exists.', 'http_code' => 409];
        }

        try {
            $db->beginTransaction();

            // Create owner user
            $pwHash      = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $firebaseUid = 'local_' . bin2hex(random_bytes(8));

            $db->prepare(
                "INSERT INTO users (name, email, password_hash, auth_provider, phone, is_verified, firebase_uid, role)
                 VALUES (?, ?, ?, 'email', ?, 1, ?, 'owner')"
            )->execute([$name, $email, $pwHash, $phone, $firebaseUid]);

            $userId = (int)$db->lastInsertId();

            // Default settings row
            $db->prepare(
                "INSERT INTO settings (user_id, business_name, default_tax) VALUES (?, ?, 18.00)"
            )->execute([$userId, $name . "'s Business"]);

            // Mark token as used
            $db->prepare(
                "UPDATE setup_tokens SET used_at = NOW(), used_by_user_id = ? WHERE token_hash = ?"
            )->execute([$userId, $hash]);

            $db->commit();

            return [
                'success' => true,
                'data'    => ['message' => 'Owner account created successfully.'],
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Setup failed: ' . $e->getMessage(), 'http_code' => 500];
        }
    }
}
PHP;

w($base . 'backend/controllers/SetupController.php', $setupController);

// ═══════════════════════════════════════════════════════════════
// 4. UserController.php — employee management (owner only)
// ═══════════════════════════════════════════════════════════════
$userController = <<<'PHP'
<?php
/**
 * UserController — owner manages employee accounts.
 * All routes require auth. Non-owner requests are rejected.
 */
class UserController
{
    private function requireOwner(): bool
    {
        $db     = getDB();
        $userId = $GLOBALS['current_user_id'] ?? null;
        if (!$userId) return false;
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return ($row && $row['role'] === 'owner');
    }

    /**
     * GET users.list  — list all users (owner sees all, employee sees only self)
     */
    public function list(array $input): array
    {
        $userId = $GLOBALS['current_user_id'] ?? null;
        if (!$userId) return ['success' => false, 'message' => 'Unauthorized.', 'http_code' => 401];

        $db = getDB();

        // Check if owner
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $me = $stmt->fetch();

        if ($me && $me['role'] === 'owner') {
            $rows = $db->query(
                "SELECT id, name, email, phone, role, is_active, created_at FROM users ORDER BY created_at ASC"
            )->fetchAll();
        } else {
            $stmt2 = $db->prepare("SELECT id, name, email, phone, role, is_active, created_at FROM users WHERE id = ?");
            $stmt2->execute([$userId]);
            $rows = $stmt2->fetchAll();
        }

        return ['success' => true, 'data' => ['users' => $rows]];
    }

    /**
     * POST users.invite
     * body: { name, email, phone?, password }
     * Owner creates an employee account directly (no link needed for employees).
     */
    public function invite(array $input): array
    {
        if (!$this->requireOwner()) {
            return ['success' => false, 'message' => 'Only the account owner can add users.', 'http_code' => 403];
        }

        $name     = trim($input['name']     ?? '');
        $email    = strtolower(trim($input['email']    ?? ''));
        $phone    = trim($input['phone']    ?? '') ?: null;
        $password = $input['password'] ?? '';

        if (!$name || !$email || !$password) {
            return ['success' => false, 'message' => 'Name, email and password are required.', 'http_code' => 400];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.', 'http_code' => 400];
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return ['success' => false, 'message' => 'Password must be at least 8 chars with 1 uppercase and 1 number.', 'http_code' => 400];
        }

        $db = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'An account with this email already exists.', 'http_code' => 409];
        }

        $pwHash      = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $firebaseUid = 'local_' . bin2hex(random_bytes(8));

        $db->prepare(
            "INSERT INTO users (name, email, password_hash, auth_provider, phone, is_verified, firebase_uid, role, is_active)
             VALUES (?, ?, ?, 'email', ?, 1, ?, 'employee', 1)"
        )->execute([$name, $email, $pwHash, $phone, $firebaseUid]);

        $newId = (int)$db->lastInsertId();

        // Employees share the owner's settings (same business)
        // No separate settings row needed — they read from the owner's settings.

        return [
            'success' => true,
            'data'    => [
                'user'    => ['id' => $newId, 'name' => $name, 'email' => $email, 'role' => 'employee'],
                'message' => "Employee account for {$name} created successfully.",
            ],
        ];
    }

    /**
     * PUT users.update
     * body: { user_id, name?, phone?, password? }
     * Owner can update any user. Employee can only update themselves (no role/status changes).
     */
    public function update(array $input): array
    {
        $actorId   = $GLOBALS['current_user_id'] ?? null;
        $targetId  = (int)($input['user_id'] ?? 0);
        if (!$actorId) return ['success' => false, 'message' => 'Unauthorized.', 'http_code' => 401];

        $db = getDB();
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$actorId]);
        $actor = $stmt->fetch();

        $isOwner = ($actor && $actor['role'] === 'owner');

        // Employees can only edit themselves
        if (!$isOwner && $targetId !== (int)$actorId) {
            return ['success' => false, 'message' => 'You can only update your own profile.', 'http_code' => 403];
        }

        $fields = []; $params = [];

        if (!empty($input['name'])) {
            $fields[] = 'name = ?'; $params[] = trim($input['name']);
        }
        if (isset($input['phone'])) {
            $fields[] = 'phone = ?'; $params[] = trim($input['phone']) ?: null;
        }
        if (!empty($input['password'])) {
            $pw = $input['password'];
            if (strlen($pw) < 8 || !preg_match('/[A-Z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
                return ['success' => false, 'message' => 'Password must be at least 8 chars with 1 uppercase and 1 number.', 'http_code' => 400];
            }
            $fields[] = 'password_hash = ?';
            $params[]  = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'Nothing to update.', 'http_code' => 400];
        }

        $fields[]  = 'updated_at = NOW()';
        $params[]  = $targetId;
        $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        return ['success' => true, 'data' => ['message' => 'User updated.']];
    }

    /**
     * POST users.deactivate
     * body: { user_id }
     * Owner only. Prevents login without deleting data.
     */
    public function deactivate(array $input): array
    {
        if (!$this->requireOwner()) {
            return ['success' => false, 'message' => 'Only the owner can deactivate users.', 'http_code' => 403];
        }

        $targetId = (int)($input['user_id'] ?? 0);
        $ownerId  = $GLOBALS['current_user_id'];

        if ($targetId === (int)$ownerId) {
            return ['success' => false, 'message' => 'You cannot deactivate your own account.', 'http_code' => 400];
        }

        $db = getDB();
        $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$targetId]);
        return ['success' => true, 'data' => ['message' => 'User deactivated.']];
    }

    /**
     * POST users.reactivate
     * body: { user_id }
     */
    public function reactivate(array $input): array
    {
        if (!$this->requireOwner()) {
            return ['success' => false, 'message' => 'Only the owner can reactivate users.', 'http_code' => 403];
        }

        $targetId = (int)($input['user_id'] ?? 0);
        $db = getDB();
        $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$targetId]);
        return ['success' => true, 'data' => ['message' => 'User reactivated.']];
    }
}
PHP;

w($base . 'backend/controllers/UserController.php', $userController);

// ═══════════════════════════════════════════════════════════════
// 5. generate_setup_link.php  — developer CLI tool
// ═══════════════════════════════════════════════════════════════
$generateScript = <<<'PHP'
<?php
/**
 * Developer CLI tool — generates a one-time owner setup link.
 *
 * Usage:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\Customized\generate_setup_link.php "Acme Corp"
 *
 * Optional: set expiry in hours (default 72)
 *   C:\xampp\php\php.exe C:\xampp\htdocs\Customized\generate_setup_link.php "Acme Corp" 48
 *
 * The script inserts a hashed token into the setup_tokens table
 * and prints the full URL to give to the client.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script must be run from the command line.');
}

require_once __DIR__ . '/backend/config.php';

$clientName  = trim($argv[1] ?? 'Client');
$expiryHours = max(1, (int)($argv[2] ?? 72));

if (empty($clientName)) {
    echo "Usage: php generate_setup_link.php \"Client Name\" [expiry_hours]\n";
    exit(1);
}

$db      = getDB();
$token   = bin2hex(random_bytes(32)); // 64-char hex — unguessable
$hash    = hash('sha256', $token);
$expires = date('Y-m-d H:i:s', time() + $expiryHours * 3600);

try {
    $db->prepare(
        "INSERT INTO setup_tokens (token_hash, client_name, expires_at) VALUES (?, ?, ?)"
    )->execute([$hash, $clientName, $expires]);
} catch (Exception $e) {
    echo "DB error: " . $e->getMessage() . "\n";
    exit(1);
}

// Build URL — adjust APP_URL in client_config.php if needed
$appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'http://localhost/Customized';
$link   = $appUrl . '/frontend/setup.html?token=' . $token;

echo "\n";
echo "=======================================================\n";
echo "  Setup link for: {$clientName}\n";
echo "  Expires in:     {$expiryHours} hours ({$expires})\n";
echo "=======================================================\n";
echo "\n  {$link}\n\n";
echo "  Send this link to the client. It works ONCE only.\n";
echo "=======================================================\n\n";
PHP;

w($base . 'generate_setup_link.php', $generateScript);

// ═══════════════════════════════════════════════════════════════
// 6. Update Customized router.php — add setup + user routes,
//    remove auth.register, add is_active check to login
// ═══════════════════════════════════════════════════════════════
$routerPath = $base . 'backend/api/router.php';
$router = file_get_contents($routerPath);

// Remove auth.register route (no public signup)
$router = str_replace(
    "        'auth.register'         => ['POST', 'AuthController@register'],\n",
    '',
    $router
);

// Add setup + user routes after the auth block
$router = str_replace(
    "        // Client routes",
    "        // Setup routes (no auth — token-protected)
        'setup.validate'        => ['GET',  'SetupController@validate'],
        'setup.complete'        => ['POST', 'SetupController@complete'],

        // User management routes (authenticated)
        'users.list'            => ['GET',  'UserController@list'],
        'users.invite'          => ['POST', 'UserController@invite'],
        'users.update'          => ['PUT',  'UserController@update'],
        'users.deactivate'      => ['POST', 'UserController@deactivate'],
        'users.reactivate'      => ['POST', 'UserController@reactivate'],

        // Client routes",
    $router
);

// Mark setup routes as public (no auth required)
// Find the public routes handling section and add setup routes
$router = str_replace(
    "    // Check if route exists",
    "    // Routes that do NOT require authentication
    \$publicRoutes = ['auth.login','auth.google','auth.otp.send','auth.otp.verify',
                     'auth.reset','auth.logout','setup.validate','setup.complete',
                     'public.invoice.get','public.invoice.order','public.invoice.pay'];

    // Check if route exists",
    $router
);

w($routerPath, $router);

// ═══════════════════════════════════════════════════════════════
// 7. Update Customized AuthController — block inactive users,
//    block auth.register if called directly
// ═══════════════════════════════════════════════════════════════
$authPath = $base . 'backend/controllers/AuthController.php';
$auth = file_get_contents($authPath);

// In login(), after password_verify success, add is_active check
$auth = str_replace(
    "            // Re-hash if algorithm needs upgrade",
    "            // Block deactivated accounts
            if (isset(\$user['is_active']) && !(bool)\$user['is_active']) {
                return ['success' => false, 'message' => 'Your account has been deactivated. Please contact the administrator.', 'http_code' => 403];
            }

            // Re-hash if algorithm needs upgrade",
    $auth
);

// Disable register() — redirect to setup flow
$auth = str_replace(
    "    public function register(array \$input): array
    {
        \$name     = trim(\$input['name']",
    "    public function register(array \$input): array
    {
        // Public registration is disabled in this edition.
        // Use the one-time setup link (generate_setup_link.php) for the owner,
        // and Settings → Users for employees.
        return ['success' => false, 'message' => 'Registration is not available. Contact your administrator.', 'http_code' => 403];

        \$name     = trim(\$input['name']",
    $auth
);

w($authPath, $auth);

// ═══════════════════════════════════════════════════════════════
// 8. Update schema.sql — add setup_tokens table, role + is_active
//    columns to users
// ═══════════════════════════════════════════════════════════════
$schemaPath = $base . 'schema.sql';
$schema = file_get_contents($schemaPath);

// Add role + is_active to users table CREATE (after firebase_uid line)
$schema = str_replace(
    "  `firebase_uid` VARCHAR(255) NOT NULL UNIQUE,\n  `created_at`",
    "  `firebase_uid` VARCHAR(255) NOT NULL UNIQUE,\n  `role` ENUM('owner','employee') NOT NULL DEFAULT 'owner',\n  `is_active` TINYINT(1) NOT NULL DEFAULT 1,\n  `password_hash` VARCHAR(255) NULL,\n  `auth_provider` ENUM('email','google') NOT NULL DEFAULT 'email',\n  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,\n  `phone` VARCHAR(50) NULL,\n  `otp_code` VARCHAR(6) NULL,\n  `otp_expires_at` DATETIME NULL,\n  `otp_purpose` ENUM('verify','reset') NULL,\n  `reset_token` VARCHAR(64) NULL,\n  `reset_token_expires_at` DATETIME NULL,\n  `google_uid` VARCHAR(255) NULL,\n  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `created_at`",
    $schema
);

// Add setup_tokens table before COMMIT
$schema = str_replace(
    "\nCOMMIT;",
    "
-- Setup tokens table (one-time owner setup links)
CREATE TABLE `setup_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE,
  `client_name` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `used_by_user_id` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;",
    $schema
);

// Remove the old sample INSERT for test user (no default users in Customized)
$schema = preg_replace(
    "/-- Insert sample data.*?COMMIT;/s",
    "COMMIT;",
    $schema
);

w($schemaPath, $schema);

// ═══════════════════════════════════════════════════════════════
// 9. Add Users tab to Settings in index.html
// ═══════════════════════════════════════════════════════════════
$indexPath = $base . 'frontend/index.html';
$index = file_get_contents($indexPath);

// Add "Users" tab to the settings sidebar tabs
$index = str_replace(
    '<button class="settings-tab" data-tab="notifications" onclick="switchSettingsTab(\'notifications\')">',
    '<button class="settings-tab" data-tab="users" onclick="switchSettingsTab(\'users\')">
                            <i class="fas fa-users"></i> Users
                        </button>
                        <button class="settings-tab" data-tab="notifications" onclick="switchSettingsTab(\'notifications\')">',
    $index
);

// Add Users panel before the closing settings content area
// Find a good anchor — the notifications tab panel end
$usersPanel = <<<'HTML'

                        <!-- ── USERS TAB ── -->
                        <div class="settings-panel" id="settings-users" style="display:none;">
                            <div class="settings-section">
                                <h3 class="settings-section-title"><i class="fas fa-users"></i> Team Members</h3>
                                <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:20px;">
                                    Add employees who can log in and use this application. Only the owner can manage users.
                                </p>

                                <!-- Add employee form (owner only) -->
                                <div id="add-user-form-wrap" style="display:none;">
                                    <div class="settings-card" style="margin-bottom:20px;">
                                        <h4 style="color:var(--text-primary);margin-bottom:16px;font-size:0.95rem;">Add New Employee</h4>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                            <div class="form-group">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" id="new-user-name" class="form-control" placeholder="Employee name">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" id="new-user-email" class="form-control" placeholder="employee@company.com">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Phone (optional)</label>
                                                <input type="tel" id="new-user-phone" class="form-control" placeholder="+91 98765 43210">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Password</label>
                                                <input type="password" id="new-user-pw" class="form-control" placeholder="Min 8 chars, 1 uppercase, 1 number">
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:10px;margin-top:14px;">
                                            <button class="btn btn-primary btn-sm" onclick="submitAddUser()" id="add-user-btn">
                                                <i class="fas fa-user-plus"></i> Add Employee
                                            </button>
                                            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('add-user-form-wrap').style.display='none'">
                                                Cancel
                                            </button>
                                        </div>
                                        <div id="add-user-msg" style="margin-top:10px;font-size:0.83rem;display:none;"></div>
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                    <span style="font-size:0.85rem;color:var(--text-secondary);" id="user-count-label">Loading...</span>
                                    <button class="btn btn-primary btn-sm" id="show-add-user-btn" style="display:none;" onclick="document.getElementById('add-user-form-wrap').style.display='block';this.style.display='none';">
                                        <i class="fas fa-plus"></i> Add Employee
                                    </button>
                                </div>

                                <div id="users-list-container">
                                    <div style="text-align:center;padding:30px;color:var(--text-secondary);">
                                        <i class="fas fa-circle-notch fa-spin"></i> Loading users...
                                    </div>
                                </div>
                            </div>
                        </div>
HTML;

// Insert users panel before notifications panel
$index = str_replace(
    '<!-- ── NOTIFICATIONS TAB',
    $usersPanel . "\n                        <!-- ── NOTIFICATIONS TAB",
    $index
);

w($indexPath, $index);

// ═══════════════════════════════════════════════════════════════
// 10. Add users JS to subscription.js (append)
// ═══════════════════════════════════════════════════════════════
$subJsPath  = $base . 'frontend/js/subscription.js';
$subJsExtra = <<<'JS'

// ─────────────────────────────────────────────────────────────
// User management (Settings → Users tab)
// ─────────────────────────────────────────────────────────────

async function loadUsersTab() {
    const container = document.getElementById('users-list-container');
    const countLabel = document.getElementById('user-count-label');
    if (!container) return;

    try {
        const token = localStorage.getItem('auth_token');
        const res   = await fetch(API_BASE + '?route=users.list', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data  = await res.json();
        if (!data.success) throw new Error(data.message);

        const users   = data.data.users || [];
        const currentId = JSON.parse(atob(token.split(':')[0] || '') || '{}')?.id;

        // Detect if current user is owner
        const me = users.find(u => String(u.id) === String(getCurrentUserId()));
        const isOwner = me && me.role === 'owner';

        if (countLabel) countLabel.textContent = users.length + ' team member' + (users.length !== 1 ? 's' : '');

        const showAddBtn = document.getElementById('show-add-user-btn');
        if (showAddBtn && isOwner) showAddBtn.style.display = '';

        container.innerHTML = users.map(u => `
            <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;background:var(--bg-card);border:1px solid var(--border);border-radius:12px;margin-bottom:8px;${!u.is_active ? 'opacity:0.55;' : ''}">
                <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:1rem;color:#fff;font-weight:700;flex-shrink:0;">
                    ${u.name.charAt(0).toUpperCase()}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;color:var(--text-primary);font-size:0.9rem;">${escHtml(u.name)} ${u.role === 'owner' ? '<span style="font-size:0.7rem;background:rgba(99,102,241,0.15);color:#a5b4fc;padding:2px 8px;border-radius:10px;margin-left:6px;">Owner</span>' : ''}</div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);">${escHtml(u.email)}</div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:0.75rem;padding:3px 10px;border-radius:10px;${u.is_active ? 'background:rgba(16,185,129,0.12);color:#6ee7b7;' : 'background:rgba(239,68,68,0.12);color:#fca5a5;'}">
                        ${u.is_active ? 'Active' : 'Inactive'}
                    </span>
                    ${isOwner && u.role !== 'owner' ? `
                    <button onclick="toggleUserActive(${u.id}, ${u.is_active ? 0 : 1}, this)"
                        style="background:none;border:1px solid var(--border);border-radius:8px;padding:5px 10px;font-size:0.75rem;color:var(--text-secondary);cursor:pointer;">
                        ${u.is_active ? 'Deactivate' : 'Reactivate'}
                    </button>` : ''}
                </div>
            </div>
        `).join('');

    } catch (err) {
        container.innerHTML = `<div style="color:#fca5a5;font-size:0.85rem;padding:12px;">${escHtml(err.message)}</div>`;
    }
}

function getCurrentUserId() {
    try {
        const token = localStorage.getItem('auth_token');
        if (!token) return null;
        const decoded = atob(token);
        return decoded.split(':')[0];
    } catch { return null; }
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function toggleUserActive(userId, newState, btn) {
    const token  = localStorage.getItem('auth_token');
    const route  = newState === 0 ? 'users.deactivate' : 'users.reactivate';
    btn.disabled = true;
    try {
        const res  = await fetch(API_BASE + '?route=' + route, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        loadUsersTab(); // refresh
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
}

async function submitAddUser() {
    const name  = document.getElementById('new-user-name').value.trim();
    const email = document.getElementById('new-user-email').value.trim();
    const phone = document.getElementById('new-user-phone').value.trim();
    const pw    = document.getElementById('new-user-pw').value;
    const msg   = document.getElementById('add-user-msg');
    const btn   = document.getElementById('add-user-btn');

    msg.style.display = 'none';
    if (!name || !email || !pw) { showMsg(msg, 'Name, email and password are required.', 'error'); return; }

    btn.disabled = true;
    try {
        const token = localStorage.getItem('auth_token');
        const res   = await fetch(API_BASE + '?route=users.invite', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, phone, password: pw })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        showMsg(msg, data.data.message, 'success');
        document.getElementById('new-user-name').value = '';
        document.getElementById('new-user-email').value = '';
        document.getElementById('new-user-phone').value = '';
        document.getElementById('new-user-pw').value = '';
        setTimeout(() => {
            document.getElementById('add-user-form-wrap').style.display = 'none';
            document.getElementById('show-add-user-btn').style.display = '';
            loadUsersTab();
        }, 1500);
    } catch (err) {
        showMsg(msg, err.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

function showMsg(el, text, type) {
    el.style.display = 'block';
    el.style.color   = type === 'error' ? '#fca5a5' : '#6ee7b7';
    el.textContent   = text;
}

// Hook into settings tab switch
const _origSwitchSettingsTab = typeof switchSettingsTab === 'function' ? switchSettingsTab : null;
document.addEventListener('DOMContentLoaded', function() {
    // Patch switchSettingsTab to load users when tab is opened
    if (typeof window.switchSettingsTab === 'function') {
        const orig = window.switchSettingsTab.bind(window);
        window.switchSettingsTab = function(tab) {
            orig(tab);
            if (tab === 'users') loadUsersTab();
        };
    }
});
JS;

$subJs = file_get_contents($subJsPath);
$subJs .= $usersJsExtra ?? $subJsExtra;
w($subJsPath, $subJs);

// ─── Print results ───
echo "<pre style='font-family:monospace;font-size:13px;'>\n";
echo "<h2>Build Result</h2>\n";
foreach ($log as $line) echo $line . "\n";
echo "\nDone.\n</pre>";
