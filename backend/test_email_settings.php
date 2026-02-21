<?php
// Quick functional test for email settings endpoints
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/api/auth.php';

// Simulate authenticated user
$GLOBALS['current_user_id'] = 1;

$controller = new SettingsController();

// Test getEmailSettings
$result = $controller->getEmailSettings([]);
echo "GET email settings: ";
echo $result['success'] ? "OK - " . json_encode($result['data']['email_settings']) : "FAIL - " . $result['message'];
echo "\n";

// Test updateEmailSettings
$result2 = $controller->updateEmailSettings([
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => 587,
    'smtp_username'   => 'test@example.com',
    'smtp_password'   => 'testpassword',
    'smtp_encryption' => 'tls',
    'smtp_from_email' => 'test@example.com',
    'smtp_from_name'  => 'Test Business',
]);
echo "UPDATE email settings: ";
echo $result2['success'] ? "OK - " . $result2['message'] : "FAIL - " . $result2['message'];
echo "\n";

// Test getEmailSettings again (should show masked password)
$result3 = $controller->getEmailSettings([]);
echo "GET after update: ";
echo $result3['success'] ? "OK - host=" . $result3['data']['email_settings']['smtp_host'] . " pass=" . $result3['data']['email_settings']['smtp_password'] : "FAIL";
echo "\n";

echo "All tests done.\n";
