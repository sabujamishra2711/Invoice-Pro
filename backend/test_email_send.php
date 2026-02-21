<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/controllers/EmailController.php';
require_once __DIR__ . '/services/SmtpMailer.php';
require_once __DIR__ . '/services/InvoiceService.php';
require_once __DIR__ . '/api/auth.php';

$GLOBALS['current_user_id'] = 1;

// First clear the email settings for this test
$db = getDB();
$db->exec("DELETE FROM email_settings WHERE user_id = 1");

$controller = new EmailController();

// Test send with no SMTP configured
$result = $controller->send([
    'invoice_id' => 1,
    'to_email'   => 'client@example.com',
    'to_name'    => 'Test Client',
    'subject'    => 'Test Invoice',
    'message'    => 'Test message',
    'attach_pdf' => false,
]);

echo "Send without SMTP: ";
echo $result['error_code'] === 'SMTP_NOT_CONFIGURED' ? "OK - Correct error returned" : "Unexpected: " . json_encode($result);
echo "\n";

// Test SMTP test connection with bad credentials (should fail gracefully)
$result2 = $controller->testConnection([
    'smtp_host'       => 'localhost',
    'smtp_port'       => 9999,
    'smtp_username'   => 'test',
    'smtp_password'   => 'test',
    'smtp_encryption' => 'none',
]);

echo "SMTP test (bad host): ";
echo !$result2['success'] ? "OK - Correctly failed: " . substr($result2['message'], 0, 60) : "Unexpected success";
echo "\n";

echo "Done.\n";
