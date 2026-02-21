<?php
/**
 * CLIENT CONFIGURATION FILE
 * ─────────────────────────────────────────────────────────────────────
 * Edit this file for EACH client deployment before going live.
 * All subscription pricing and branding is controlled from here.
 * ─────────────────────────────────────────────────────────────────────
 */

// ── CLIENT BRANDING ───────────────────────────────────────────────────

// Client's business name — shown in the app title and sidebar
define('CLIENT_NAME',    'My Business');

// App title shown in browser tab and header
define('APP_TITLE',      'InvoicePro');

// Primary brand color (hex) — used for buttons, highlights
define('PRIMARY_COLOR',  '#6366f1');

// ── RAZORPAY CREDENTIALS (PER CLIENT) ────────────────────────────────
// Each client deployment uses their own Razorpay account so money goes
// directly to them. Replace these with the client's Razorpay keys.

define('RAZORPAY_KEY_ID',     'rzp_test_REPLACE_WITH_CLIENT_KEY');
define('RAZORPAY_KEY_SECRET', 'REPLACE_WITH_CLIENT_SECRET');

// ── SUBSCRIPTION PRICING ──────────────────────────────────────────────

// One-time setup / installation fee (for your records — not charged in-app)
// You collect this separately before deployment.
define('SETUP_FEE',           28000);  // ₹28,000

// Free period in months after deployment (no payment required)
define('FREE_PERIOD_MONTHS',  6);

// Monthly renewal fee (in INR) after free period ends
define('RENEWAL_FEE_INR',     1499);   // ₹1,499/month

// Monthly renewal fee in paise (Razorpay uses paise)
define('RENEWAL_FEE_PAISE',   149900); // ₹1,499 × 100

// Grace period (days) after expiry before read-only mode kicks in
define('GRACE_PERIOD_DAYS',   7);

// ── DEPLOYMENT DATE ───────────────────────────────────────────────────
// Set this to the date you deploy for this client (YYYY-MM-DD).
// The 6-month free period is calculated from this date.
// After FREE_PERIOD_MONTHS from this date, the client will see renewal prompts.

define('DEPLOYMENT_DATE', '2026-02-21');
