<?php
require_once __DIR__ . '/config.php';
$db = getDB();

$sql1 = "CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL DEFAULT '',
  `smtp_port` int(5) NOT NULL DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL DEFAULT '',
  `smtp_password` varchar(500) NOT NULL DEFAULT '',
  `smtp_encryption` enum('none','tls','ssl') NOT NULL DEFAULT 'tls',
  `smtp_from_email` varchar(255) NOT NULL DEFAULT '',
  `smtp_from_name` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_settings_user_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sql2 = "CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email_logs_user` (`user_id`),
  KEY `email_logs_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $db->exec($sql1);
    echo "email_settings table: OK\n";
} catch(Exception $e) {
    echo "email_settings error: " . $e->getMessage() . "\n";
}

try {
    $db->exec($sql2);
    echo "email_logs table: OK\n";
} catch(Exception $e) {
    echo "email_logs error: " . $e->getMessage() . "\n";
}
echo "Done.\n";
