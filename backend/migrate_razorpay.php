<?php
require_once __DIR__ . '/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pdo->exec("
CREATE TABLE IF NOT EXISTS razorpay_orders (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    razorpay_order_id   VARCHAR(64) NOT NULL,
    razorpay_payment_id VARCHAR(64) DEFAULT NULL,
    plan          VARCHAR(32) NOT NULL,
    amount        INT UNSIGNED NOT NULL COMMENT 'Amount in paise',
    currency      VARCHAR(8) NOT NULL DEFAULT 'INR',
    status        ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
    extra_clients  INT UNSIGNED NOT NULL DEFAULT 0,
    extra_invoices INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rp_order (razorpay_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "razorpay_orders table OK\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS plan_subscriptions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL UNIQUE,
    plan       VARCHAR(32) NOT NULL DEFAULT 'pro',
    max_clients  INT NOT NULL DEFAULT 10,
    max_invoices INT NOT NULL DEFAULT 20,
    activated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at   TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "plan_subscriptions table OK\n";

// Seed existing users into plan_subscriptions
$users = $pdo->query("SELECT id, plan FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    $pdo->prepare("
        INSERT IGNORE INTO plan_subscriptions (user_id, plan, max_clients, max_invoices)
        VALUES (:uid, :plan,
            CASE :plan2 WHEN 'professional' THEN 50 WHEN 'enterprise' THEN -1 ELSE 10 END,
            CASE :plan3 WHEN 'professional' THEN 100 WHEN 'enterprise' THEN -1 ELSE 20 END)
    ")->execute([':uid'=>$u['id'],':plan'=>$u['plan'],':plan2'=>$u['plan'],':plan3'=>$u['plan']]);
    echo "  Seeded user #{$u['id']} plan={$u['plan']}\n";
}
echo "Done.\n";
