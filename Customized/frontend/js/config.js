// API Configuration — works with XAMPP Apache
const CURRENT_ORIGIN = window.location.origin;
const API_BASE_URL = CURRENT_ORIGIN + '/invoice-management/backend/api';

// Firebase config is injected by firebase-config.php (loaded before this file).
const FIREBASE_CONFIG = (typeof window !== 'undefined' && window.FIREBASE_CONFIG) ? window.FIREBASE_CONFIG : {
    apiKey: '', authDomain: '', projectId: '', appId: ''
};

// Initialise Firebase (compat SDK) — safe to call multiple times thanks to apps.length guard
if (typeof firebase !== 'undefined' && !firebase.apps.length && FIREBASE_CONFIG.apiKey) {
    firebase.initializeApp(FIREBASE_CONFIG);
}
