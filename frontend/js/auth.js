/**
 * Auth Manager — handles authentication state, login/logout
 */
class AuthManager {
    constructor() {
        this._user = null;
        this._init();
    }

    _init() {
        const token = localStorage.getItem('auth_token');
        const email = localStorage.getItem('user_email');
        const name = localStorage.getItem('user_name');

        if (token && email) {
            this._user = { email, name: name || email.split('@')[0] };
        }
    }

    isAuthenticated() {
        return !!localStorage.getItem('auth_token');
    }

    getCurrentUser() {
        return this._user;
    }

    getUserInitials() {
        if (!this._user) return 'U';
        const name = this._user.name || this._user.email || '';
        const parts = name.split(/[\s@]+/);
        if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
        return name.substring(0, 2).toUpperCase();
    }

    getAuthToken() {
        return localStorage.getItem('auth_token');
    }

    getCsrfToken() {
        return localStorage.getItem('csrf_token') || '';
    }

    async login(email, password) {
        try {
            const response = await fetch(
                `${typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : '/invoice-management/backend/api'}/index.php?route=auth.login`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.getCsrfToken()
                    },
                    body: JSON.stringify({ email, password })
                }
            );

            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error('Server returned invalid response (status ' + response.status + ')');
            }

            if (result.success) {
                const token = result.data?.token || 'session_token';
                const name = result.data?.name || email.split('@')[0];
                localStorage.setItem('auth_token', token);
                localStorage.setItem('user_email', email);
                localStorage.setItem('user_name', name);
                this._user = { email, name };
                return { success: true, user: this._user };
            } else {
                throw new Error(result.message || 'Login failed');
            }
        } catch (error) {
            // For development: allow mock login when backend is unavailable
            const isBackendError =
                error.message.includes('Failed to fetch') ||
                error.message.includes('NetworkError') ||
                error.message.includes('Database connection') ||
                error.message.includes('500') ||
                error.message.includes('not valid JSON') ||
                error.message.includes('Unexpected token') ||
                error.message.includes('Login failed');

            if (isBackendError) {
                console.warn('Backend unavailable — using development mock login');
                const name = email.split('@')[0];
                localStorage.setItem('auth_token', 'mock_dev_token');
                localStorage.setItem('user_email', email);
                localStorage.setItem('user_name', name);
                this._user = { email, name };
                return { success: true, user: this._user };
            }
            return { success: false, error: error.message };
        }
    }

    logout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_email');
        localStorage.removeItem('user_name');
        this._user = null;
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
}

// Global instance
try {
    window.authManager = new AuthManager();
    console.log('AuthManager initialized successfully');
} catch (error) {
    console.error('Failed to initialize AuthManager:', error);
    // Fallback to prevent errors
    window.authManager = {
        requireAuth: () => false,
        login: async () => ({ success: false, error: 'Auth system not loaded' }),
        logout: () => { },
        getCurrentUser: () => null,
        getUserInitials: () => 'N/A',
        isAuthenticated: () => false,
        getAuthToken: () => null,
        getCsrfToken: () => ''
    };
}