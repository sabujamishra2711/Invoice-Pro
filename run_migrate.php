<?php
require_once __DIR__ . '/backend/config.php';
$db = getDB();

$stmts = [
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `password_hash` VARCHAR(255) NULL AFTER `email`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `auth_provider` ENUM('email','google') NOT NULL DEFAULT 'email' AFTER `password_hash`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `google_uid` VARCHAR(255) NULL AFTER `auth_provider`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NULL AFTER `google_uid`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `phone`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `otp_code` VARCHAR(6) NULL AFTER `is_verified`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `otp_expires_at` DATETIME NULL AFTER `otp_code`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `otp_purpose` ENUM('verify','reset') NULL AFTER `otp_expires_at`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(64) NULL AFTER `otp_purpose`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `reset_token_expires_at` DATETIME NULL AFTER `reset_token`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // Mark existing dev users as verified
    "UPDATE `users` SET `is_verified` = 1, `auth_provider` = 'email' WHERE `otp_code` IS NULL",
];

foreach ($stmts as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 60) . "\n";
    } catch (Exception $e) {
        echo "SKIP/ERR: " . $e->getMessage() . "\n";
    }
}

// Show final schema
$cols = $db->query("SHOW COLUMNS FROM users")->fetchAll();
echo "\nusers columns:\n";
foreach ($cols as $c) {
    echo "  {$c['Field']} {$c['Type']} {$c['Null']} {$c['Default']}\n";
}
