<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

// Add public_token to invoices (unique random token for shareable link)
$db->exec("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS public_token VARCHAR(64) NULL UNIQUE AFTER status");

// Add online_payment_ref to payments (to track Razorpay payment IDs from public pay)
$db->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS online_payment_ref VARCHAR(128) NULL AFTER reference");
$db->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER online_payment_ref");

// Index for fast token lookup
$existing = $db->query("SHOW INDEX FROM invoices WHERE Key_name = 'idx_invoices_public_token'")->fetch();
if (!$existing) {
    $db->exec("CREATE INDEX idx_invoices_public_token ON invoices(public_token)");
}

echo "Migration complete.\n";
