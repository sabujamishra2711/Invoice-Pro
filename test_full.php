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

// Simulate $_GET for routes that use $_GET['id']
function callRouteWithGet(string $method, string $route, array $input = [], array $get = []): array {
    global $userId;
    $GLOBALS['current_user_id'] = $userId;
    // Merge into $_GET so controllers that use $_GET['id'] work
    foreach ($get as $k => $v) $_GET[$k] = $v;
    $result = routeRequest($method, $route, $input);
    foreach (array_keys($get) as $k) unset($_GET[$k]);
    return $result;
}

$pass = 0; $fail = 0;
function check(string $label, array $result, bool $expectSuccess = true): void {
    global $pass, $fail;
    $ok = (bool)($result['success'] ?? false) === $expectSuccess;
    if ($ok) { echo "  PASS: $label\n"; $pass++; }
    else      { echo "  FAIL: $label => " . json_encode(array_intersect_key($result, array_flip(['success','message','error_code']))) . "\n"; $fail++; }
}

echo "Testing as user_id=$userId\n\n";

// Create a client
$cr = callRouteWithGet('POST', 'client.create', ['name' => 'Test Client', 'email' => 'tc@example.com']);
$clientId = $cr['data']['client']['id'] ?? null;
check("client.create -> id=$clientId", $cr);

if ($clientId) {
    check('client.get', callRouteWithGet('GET', 'client.get', [], ['id' => $clientId]));
    check('client.update', callRouteWithGet('PUT', 'client.update', ['name' => 'Updated', 'email' => 'tc@example.com'], ['id' => $clientId]));
}

// Create invoice
$ir = callRouteWithGet('POST', 'invoice.create', [
    'client_id' => (int)$clientId, 'issue_date' => date('Y-m-d'),
    'due_date'  => date('Y-m-d', strtotime('+30 days')), 'currency' => 'INR',
    'items' => [['description' => 'Svc', 'quantity' => 1, 'rate' => 1000, 'tax_percent' => 18]]
]);
$invId = $ir['data']['invoice']['id'] ?? null;
check("invoice.create -> id=$invId", $ir);

if ($invId) {
    check('invoice.get', callRouteWithGet('GET', 'invoice.get', [], ['id' => $invId]));
    check('invoice.update', callRouteWithGet('PUT', 'invoice.update', [
        'client_id' => (int)$clientId, 'issue_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')), 'currency' => 'INR',
        'items' => [['description' => 'Svc v2', 'quantity' => 2, 'rate' => 500, 'tax_percent' => 18]]
    ], ['id' => $invId]));
    check('invoice.duplicate', callRouteWithGet('POST', 'invoice.duplicate', [], ['id' => $invId]));
}

// Recurring
$rr = callRouteWithGet('POST', 'recurring.create', [
    'client_id' => (int)$clientId, 'title' => 'Monthly',
    'frequency' => 'monthly', 'next_date' => date('Y-m-d'), 'currency' => 'INR',
    'items' => [['description' => 'Fee', 'quantity' => 1, 'rate' => 999, 'tax_percent' => 0]]
]);
$recId = $rr['data']['id'] ?? null;
check("recurring.create -> id=$recId", $rr);
if ($recId) {
    check('recurring.get',    callRouteWithGet('GET', 'recurring.get', [], ['id' => $recId]));
    check('recurring.pause',  callRouteWithGet('POST', 'recurring.pause', [], ['id' => $recId]));
    check('recurring.resume', callRouteWithGet('POST', 'recurring.resume', [], ['id' => $recId]));
    check('recurring.delete', callRouteWithGet('DELETE', 'recurring.delete', [], ['id' => $recId]));
}

// Expenses
$er = callRouteWithGet('POST', 'expense.create', [
    'expense_date' => date('Y-m-d'), 'category' => 'Software',
    'amount' => 250, 'vendor' => 'Test', 'payment_method' => 'credit_card'
]);
$expId = $er['data']['id'] ?? null;
check("expense.create -> id=$expId", $er);
if ($expId) {
    check('expense.get',    callRouteWithGet('GET', 'expense.get', [], ['id' => $expId]));
    check('expense.update', callRouteWithGet('PUT', 'expense.update', [
        'amount' => 300, 'expense_date' => date('Y-m-d'), 'vendor' => 'Updated', 'category' => 'Software'
    ], ['id' => $expId]));
    check('expense.delete', callRouteWithGet('DELETE', 'expense.delete', [], ['id' => $expId]));
}

// Public invoice
if ($invId) {
    $tr = callRouteWithGet('POST', 'public.invoice.token.generate', [], ['id' => $invId]);
    check('public.invoice.token.generate', $tr);
    $pubToken = $tr['data']['token'] ?? null;
    if ($pubToken) {
        $saved = $userId;
        $GLOBALS['current_user_id'] = null;
        $_GET['token'] = $pubToken;
        check('public.invoice.get', callRouteWithGet('GET', 'public.invoice.get', [], ['token' => $pubToken]));
        $GLOBALS['current_user_id'] = $saved;
        check('public.invoice.token.revoke', callRouteWithGet('DELETE', 'public.invoice.token.revoke', [], ['id' => $invId]));
    }
}

// Auth
check('auth.logout', callRouteWithGet('POST', 'auth.logout', []));

// Payments
if ($invId) {
    $pr = callRouteWithGet('POST', 'payment.create', [
        'invoice_id' => (int)$invId, 'amount' => 500,
        'payment_date' => date('Y-m-d'), 'method' => 'bank_transfer'
    ]);
    check('payment.create', $pr);
}

// Cleanup
if ($invId) callRouteWithGet('DELETE', 'invoice.delete', [], ['id' => $invId]);
if ($clientId) callRouteWithGet('DELETE', 'client.delete', [], ['id' => $clientId]);

echo "\n=== RESULTS: $pass passed, $fail failed ===\n";
if ($fail === 0) echo "ALL TESTS PASSED!\n";
