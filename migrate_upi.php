<?php
require_once __DIR__ . '/backend/config.php';
$db = getDB();

$cols = $db->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
echo "Current columns: " . implode(', ', $cols) . "\n";

if (!in_array('upi_id', $cols)) {
    $db->exec("ALTER TABLE settings ADD COLUMN upi_id VARCHAR(100) NULL DEFAULT NULL");
    echo "Added upi_id\n";
} else {
    echo "upi_id already exists\n";
}

if (!in_array('upi_qr_path', $cols)) {
    $db->exec("ALTER TABLE settings ADD COLUMN upi_qr_path VARCHAR(255) NULL DEFAULT NULL");
    echo "Added upi_qr_path\n";
} else {
    echo "upi_qr_path already exists\n";
}

echo "Done.\n";
