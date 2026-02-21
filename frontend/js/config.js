// API Configuration — works with XAMPP Apache
const CURRENT_ORIGIN = window.location.origin;
const API_BASE_URL = CURRENT_ORIGIN + '/invoice-management/backend/api';

// Firebase config is injected by firebase-config.php (loaded before this file).
// window.FIREBASE_CONFIG is set by that script tag.
const FIREBASE_CONFIG = (typeof window !== 'undefined' && window.FIREBASE_CONFIG) ? window.FIREBASE_CONFIG : {
    apiKey: '', authDomain: '', projectId: '', appId: ''
};
