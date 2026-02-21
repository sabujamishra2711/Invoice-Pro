<?php
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/api/response.php';
require_once __DIR__ . '/backend/api/auth.php';
require_once __DIR__ . '/backend/api/router.php';
if (file_exists(__DIR__ . '/backend/services/Logger.php')) { require_once __DIR__ . '/backend/services/Logger.php'; Logger::init(); }

$db = getDB();
$user = $db->query("SELECT id FROM users ORDER BY id DESC LIMIT 1")->fetch();
$userId = (int)$user['id'];
$GLOBALS['current_user_id'] = $userId;

function callRoute(string $method, string $route, array $input = []): array {
    global $userId;
    $GLOBALS['current_user_id'] = $userId;
    return routeRequest($method, $route, $input);
}

// Create a test client first
$cr = callRoute('POST', 'client.create', ['name' => 'Debug Client', 'email' => 'debug@example.com']);
$clientId = $cr['data']['id'] ?? null;
echo "Client created: id=$clientId\n";

// Test invoice.create - get validation errors
$ir = callRoute('POST', 'invoice.create', [
    'client_id' => $clientId, 'issue_date' => date('Y-m-d'),
    'due_date'  => date('Y-m-d', strtotime('+30 days')), 'currency' => 'INR',
    'notes' => 'API test', 'status' => 'draft',
    'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 18]]
]);
echo "invoice.create: " . json_encode($ir) . "\n\n";

// Test recurring.create
$rr = callRoute('POST', 'recurring.create', [
    'client_id' => $clientId, 'title' => 'Monthly Svc',
    'frequency' => 'monthly', 'start_date' => date('Y-m-d'), 'currency' => 'INR',
    'items' => [['description' => 'Fee', 'quantity' => 1, 'unit_price' => 999, 'tax_rate' => 0]]
]);
echo "recurring.create: " . json_encode($rr) . "\n\n";

// Test expense.create — get exact validation error
$er = callRoute('POST', 'expense.create', [
    'amount' => 250, 'date' => date('Y-m-d'), 'vendor' => 'Test Vendor',
    'description' => 'Software', 'payment_method' => 'credit_card'
]);
echo "expense.create (no category): " . json_encode($er) . "\n";

// Test expense.create with category=''
$er2 = callRoute('POST', 'expense.create', [
    'amount' => 250, 'date' => date('Y-m-d'), 'vendor' => 'Test Vendor',
    'description' => 'Software', 'payment_method' => 'credit_card', 'category' => ''
]);
echo "expense.create (empty category): " . json_encode($er2) . "\n";

// Test expense.create with category='general'
$er3 = callRoute('POST', 'expense.create', [
    'amount' => 250, 'date' => date('Y-m-d'), 'vendor' => 'Test Vendor',
    'description' => 'Software', 'payment_method' => 'credit_card', 'category' => 'General'
]);
echo "expense.create (category=General): " . json_encode($er3) . "\n";

// Cleanup
if ($clientId) callRoute('DELETE', 'client.delete', ['id' => $clientId]);
