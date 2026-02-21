<?php
error_reporting(0);
$h = file_get_contents(__DIR__ . '/frontend/index.html');

// Find expense-billable usage
preg_match_all('/.{0,100}expense-billable.{0,100}/', $h, $m);
echo "=== expense-billable occurrences ===\n";
foreach ($m[0] as $hit) echo trim($hit) . "\n\n";

// Also show expense-category select
preg_match('/<select[^>]*id="expense-category"[^>]*>.*?<\/select>/si', $h, $sel);
echo "\n=== expense-category select ===\n";
echo ($sel[0] ?? 'NOT FOUND') . "\n";

// Show expense stats element IDs
preg_match_all('/id="expense-[^"]+"/i', $h, $eids);
echo "\n=== expense IDs ===\n";
echo implode("\n", array_unique($eids[0])) . "\n";

// Check PublicInvoiceController for how it reads invoice_id
$pic = file_get_contents(__DIR__ . '/backend/controllers/PublicInvoiceController.php');
preg_match('/generateToken[^}]{0,500}/s', $pic, $gt);
echo "\n=== generateToken method ===\n" . ($gt[0] ?? 'NOT FOUND') . "\n";

// Check RecurringInvoiceController for how it reads client_id
$ric = file_get_contents(__DIR__ . '/backend/controllers/RecurringInvoiceController.php');
preg_match('/function create[^}]{0,800}/s', $ric, $rc);
echo "\n=== RecurringController::create ===\n" . ($rc[0] ?? 'NOT FOUND') . "\n";

// Settings update - check POST vs PUT
$sc = file_get_contents(__DIR__ . '/backend/controllers/SettingsController.php');
preg_match('/function update[^}]{0,500}/s', $sc, $su);
echo "\n=== SettingsController::update ===\n" . ($su[0] ?? 'NOT FOUND') . "\n";
