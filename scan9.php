<?php
$ic = file_get_contents(__DIR__ . '/backend/controllers/InvoiceController.php');
// Find create method
preg_match('/function create[^{]*\{.+?(?=\n    (?:public|private|protected))/s', $ic, $m);
echo "=== InvoiceController::create (truncated) ===\n" . substr($m[0] ?? 'NOT FOUND', 0, 2000) . "\n\n";

// Check InvoiceService::createInvoice item fields
$is = file_get_contents(__DIR__ . '/backend/services/InvoiceService.php');
preg_match('/function createInvoice[^{]*\{.+?(?=\n    (?:public|private|protected))/s', $is, $m2);
echo "=== InvoiceService::createInvoice (truncated) ===\n" . substr($m2[0] ?? 'NOT FOUND', 0, 2000) . "\n";

// Router check for settings.update method
$r = file_get_contents(__DIR__ . '/backend/api/router.php');
preg_match("/settings\.update[^,\n]+/", $r, $m3);
echo "\n=== settings.update route ===\n" . ($m3[0] ?? 'NOT FOUND') . "\n";

// Check auth.logout in AuthController
preg_match('/function logout[^{]*\{.+?(?=\n    (?:public|private|protected|\}))/s', file_get_contents(__DIR__ . '/backend/controllers/AuthController.php'), $m4);
echo "\n=== AuthController::logout ===\n" . ($m4[0] ?? 'NOT FOUND') . "\n";
