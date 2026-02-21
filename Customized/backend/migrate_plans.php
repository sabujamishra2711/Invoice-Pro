<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Add plan column to users if it doesn't exist
$cols = $db->query("SHOW COLUMNS FROM users LIKE 'plan'")->fetchAll();
if (!$cols) {
    $db->exec("ALTER TABLE users ADD COLUMN `plan` ENUM('pro','professional','enterprise') NOT NULL DEFAULT 'pro' AFTER `email`");
    echo "Added `plan` column to users.\n";
} else {
    echo "`plan` column already exists.\n";
}

// Show current users
$users = $db->query("SELECT id, name, email, plan FROM users")->fetchAll();
foreach ($users as $u) {
    echo "  User #{$u['id']} {$u['name']} <{$u['email']}> — plan: {$u['plan']}\n";
}
echo "Done.\n";
