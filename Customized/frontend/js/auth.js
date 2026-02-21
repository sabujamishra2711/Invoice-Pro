/**
 * AuthManager — real bcrypt + Firebase Google auth.
 * Stores: auth_token, user_email, user_name, auth_provider, user_id, user_phone, user_verified
 */
class AuthManager {
    constructor() {
        this._user = null;
        this._init();
    }

    _init() {
        const token    = localStorage.getItem('auth_token');
        const email    = localStorage.getItem('user_email');
        const name     = localStorage.getItem('user_name');
        const provider = localStorage.getItem('auth_provider') || 'email';
        const phone    = localStorage.getItem('user_phone') || '';
        const verified = localStorage.getItem('user_verified') === '1';

        if (token && email) {
            this._user = { email, name: name || email.split('@')[0], auth_provider: provider, phone, is_verified: verified };
        }
    }

    isAuthenticated()  { return !!localStorage.getItem('auth_token'); }
    getCurrentUser()   { return this._user; }
    getAuthToken()     { return localStorage.getItem('auth_token'); }
    getCsrfToken()     { return localStorage.getItem('csrf_token') || ''; }
    isGoogleUser()     { return localStorage.getItem('auth_provider') === 'google'; }

    getUserInitials() {
        if (!this._user) return 'U';
        const name  = this._user.name || this._user.email || '';
        const parts = name.split(/[\s@]+/);
        if (parts.length >= 2 && parts[0] && parts[1]) return (parts[0][0] + parts[1][0]).toUpperCase();
        return name.substring(0, 2).toUpperCase() || 'U';
    }

    /** Standard email/password login — calls backend which does bcrypt verify */
    async login(email, password) {
        try {
            const res = await this._post('auth.login', { email, password });

            if (!res.success) throw new Error(res.message || 'Login failed');

            this._persist(res.data);

            // If unverified, caller handles OTP overlay
            return { success: true, user: this._user, needsVerify: !!res.data.needs_verify };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /** Register new account — calls backend which bcrypt-hashes the password */
    async register(name, email, password, phone) {
        try {
            const res = await this._post('auth.register', { name, email, password, phone: phone || '' });
            if (!res.success) throw new Error(res.message || 'Registration failed');
            this._persist(res.data);
            return { success: true, user: this._user, needsVerify: true };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /** Google sign-in via Firebase popup, then verify ID token on backend */
    async loginWithGoogle() {
        if (typeof firebase === 'undefined' || !firebase.apps.length) {
            return { success: false, error: 'Firebase not initialised. Check your Firebase configuration.' };
        }
        try {
            const provider = new firebase.auth.GoogleAuthProvider();
            provider.addScope('email');
            provider.addScope('profile');

            const result  = await firebase.auth().signInWithPopup(provider);
            const idToken = await result.user.getIdToken(/* forceRefresh */ true);

            const res = await this._post('auth.google', { id_token: idToken });
            if (!res.success) throw new Error(res.message || 'Google sign-in failed');

            this._persist(res.data);
            return { success: true, user: this._user };
        } catch (err) {
            // Firebase-specific error codes
            if (err.code === 'auth/popup-closed-by-user') {
                return { success: false, error: 'Sign-in cancelled.' };
            }
            if (err.code === 'auth/popup-blocked') {
                return { success: false, error: 'Pop-up was blocked. Please allow pop-ups for this site.' };
            }
            return { success: false, error: err.message };
        }
    }

    /** Send OTP — for email verification or password reset */
    async sendOtp(email, purpose = 'verify') {
        try {
            const res = await this._post('auth.otp.send', { email, purpose });
            return { success: res.success, message: res.data?.message || res.message };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /** Verify OTP */
    async verifyOtp(email, otp, purpose = 'verify') {
        try {
            const res = await this._post('auth.otp.verify', { email, otp, purpose });
            if (!res.success) throw new Error(res.message || 'Invalid OTP');
            if (res.data?.token) {
                localStorage.setItem('auth_token', res.data.token);
                localStorage.setItem('user_verified', '1');
                if (this._user) this._user.is_verified = true;
            }
            return { success: true, resetToken: res.data?.reset_token || null };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /** Reset password after OTP verification */
    async resetPassword(email, resetToken, newPassword) {
        try {
            const res = await this._post('auth.reset', { email, reset_token: resetToken, new_password: newPassword });
            if (!res.success) throw new Error(res.message || 'Reset failed');
            return { success: true };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /** Change password (authenticated) — blocked for Google users */
    async changePassword(currentPassword, newPassword) {
        if (this.isGoogleUser()) {
            return { success: false, error: 'Google accounts cannot change password here.' };
        }
        try {
            const res = await this._authPost('auth.password.change', {
                current_password: currentPassword,
                new_password: newPassword
            });
            if (!res.success) throw new Error(res.message || 'Password change failed');
            return { success: true };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    /** Update profile — email locked for Google users */
    async updateProfile(data) {
        if (this.isGoogleUser()) {
            // Strip email from payload — Google users cannot change email
            delete data.email;
        }
        try {
            const res = await this._authPost('auth.profile.update', data);
            if (!res.success) throw new Error(res.message || 'Update failed');
            // Refresh stored values
            const u = res.data.user;
            localStorage.setItem('user_name',  u.name  || '');
            localStorage.setItem('user_phone', u.phone || '');
            if (!this.isGoogleUser() && u.email) localStorage.setItem('user_email', u.email);
            this._init(); // re-hydrate _user
            return { success: true, user: u };
        } catch (err) {
            return { success: false, error: err.message };
        }
    }

    logout() {
        ['auth_token','user_email','user_name','auth_provider',
         'user_id','user_phone','user_verified','business_logo'].forEach(k => localStorage.removeItem(k));
        this._user = null;
        // Sign out of Firebase too (if active)
        if (typeof firebase !== 'undefined' && firebase.apps.length) {
            firebase.auth().signOut().catch(() => {});
        }
        window.location.href = '/invoice-management/login';
    }

    requireAuth() {
        if (!this.isAuthenticated()) {
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/invoice-management/login';
            }
            return false;
        }
        return true;
    }

    // ── private ──────────────────────────────────────────────────────────────

    _persist(data) {
        const u = data.user || {};
        localStorage.removeItem('business_logo'); // clear any stale logo from a previous session
        localStorage.setItem('auth_token',    data.token              || '');
        localStorage.setItem('user_email',    u.email                 || '');
        localStorage.setItem('user_name',     u.name                  || '');
        localStorage.setItem('auth_provider', u.auth_provider         || 'email');
        localStorage.setItem('user_id',       String(u.id             || ''));
        localStorage.setItem('user_phone',    u.phone                 || '');
        localStorage.setItem('user_verified', u.is_verified ? '1' : '0');
        this._init();
    }

    _apiBase() {
        return (typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : '/invoice-management/backend/api') + '/index.php';
    }

    async _post(route, body) {
        const r = await fetch(`${this._apiBase()}?route=${route}`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body)
        });
        const text = await r.text();
        try { return JSON.parse(text); } catch { throw new Error('Server error (' + r.status + ')'); }
    }

    async _authPost(route, body) {
        const r = await fetch(`${this._apiBase()}?route=${route}`, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Authorization': 'Bearer ' + this.getAuthToken()
            },
            body: JSON.stringify(body)
        });
        const text = await r.text();
        try { return JSON.parse(text); } catch { throw new Error('Server error (' + r.status + ')'); }
    }
}

// Global singleton
try {
    window.authManager = new AuthManager();
} catch (e) {
    console.error('AuthManager init failed:', e);
    window.authManager = {
        requireAuth:    () => false,
        login:          async () => ({ success: false, error: 'Auth not loaded' }),
        loginWithGoogle:async () => ({ success: false, error: 'Auth not loaded' }),
        register:       async () => ({ success: false, error: 'Auth not loaded' }),
        logout:         () => {},
        getCurrentUser: () => null,
        getUserInitials:() => 'U',
        isAuthenticated:() => false,
        isGoogleUser:   () => false,
        getAuthToken:   () => null,
        getCsrfToken:   () => '',
        sendOtp:        async () => ({ success: false }),
        verifyOtp:      async () => ({ success: false }),
        resetPassword:  async () => ({ success: false }),
        changePassword: async () => ({ success: false }),
        updateProfile:  async () => ({ success: false }),
    };
}
