<?php
// Comprehensive API test
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'backend/config.php';
$db = getDB();

$errors = [];
$ok = [];

// 1. Check all controllers exist
$controllers = [
    'backend/controllers/InvoiceController.php',
    'backend/controllers/ClientController.php',
    'backend/controllers/PaymentController.php',
    'backend/controllers/SettingsController.php',
    'backend/controllers/RecurringController.php',
    'backend/controllers/ExpenseController.php',
    'backend/controllers/PublicInvoiceController.php',
    'backend/controllers/DashboardController.php',
    'backend/controllers/AuthController.php',
];
foreach ($controllers as $c) {
    $path = __DIR__ . '/' . $c;
    if (file_exists($path)) {
        $ok[] = "EXISTS: $c";
    } else {
        $errors[] = "MISSING: $c";
    }
}

// 2. Check DB tables
$tables = ['users','clients','invoices','invoice_items','payments','settings',
           'recurring_invoices','recurring_invoice_items','expenses','expense_categories'];
foreach ($tables as $t) {
    try {
        $db->query("SELECT 1 FROM `$t` LIMIT 1");
        $ok[] = "TABLE OK: $t";
    } catch (Exception $e) {
        $errors[] = "TABLE MISSING/ERROR: $t — " . $e->getMessage();
    }
}

// 3. Check invoices table for public_token column
$cols = $db->query("SHOW COLUMNS FROM invoices")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('public_token', $cols)) {
    $ok[] = "COLUMN OK: invoices.public_token";
} else {
    $errors[] = "COLUMN MISSING: invoices.public_token";
}

// 4. Check payments table for online_payment_ref and source
$pcols = $db->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
foreach (['online_payment_ref', 'source'] as $col) {
    if (in_array($col, $pcols)) {
        $ok[] = "COLUMN OK: payments.$col";
    } else {
        $errors[] = "COLUMN MISSING: payments.$col";
    }
}

// 5. Check router.php for all expected routes
$router = file_get_contents(__DIR__ . '/backend/api/router.php');
$routes = [
    'invoice.list', 'invoice.get', 'invoice.create', 'invoice.update', 'invoice.delete',
    'invoice.duplicate', 'invoice.email.send', 'invoice.export',
    'client.list', 'client.get', 'client.create', 'client.update', 'client.delete',
    'payment.list', 'payment.create', 'payment.export',
    'settings.get', 'settings.update',
    'recurring.list', 'recurring.create', 'recurring.update', 'recurring.delete',
    'expense.list', 'expense.get', 'expense.create', 'expense.update', 'expense.delete',
    'expense.summary', 'expense.categories',
    'public.invoice.view', 'public.invoice.pay.create', 'public.invoice.pay.verify',
    'public.invoice.token.generate', 'public.invoice.token.revoke',
    'dashboard.stats',
];
foreach ($routes as $route) {
    if (strpos($router, $route) !== false) {
        $ok[] = "ROUTE OK: $route";
    } else {
        $errors[] = "ROUTE MISSING: $route";
    }
}

// 6. Check index.php public routes
$index = file_get_contents(__DIR__ . '/backend/api/index.php');
$publicRoutes = ['public.invoice.view', 'public.invoice.pay.create', 'public.invoice.pay.verify'];
foreach ($publicRoutes as $r) {
    if (strpos($index, $r) !== false) {
        $ok[] = "PUBLIC ROUTE OK: $r";
    } else {
        $errors[] = "PUBLIC ROUTE MISSING IN INDEX: $r";
    }
}

// 7. Check pay.html exists
if (file_exists(__DIR__ . '/frontend/pay.html')) {
    $ok[] = "FILE OK: frontend/pay.html";
} else {
    $errors[] = "FILE MISSING: frontend/pay.html";
}

// 8. Check JS files exist
$jsFiles = ['frontend/js/ui.js','frontend/js/api.js','frontend/js/main.js',
            'frontend/js/expense.js','frontend/js/recurring.js'];
foreach ($jsFiles as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        $ok[] = "JS OK: $f";
    } else {
        $errors[] = "JS MISSING: $f";
    }
}

// 9. Check api.js has generatePublicLink
$apiJs = file_get_contents(__DIR__ . '/frontend/js/api.js');
if (strpos($apiJs, 'generatePublicLink') !== false) {
    $ok[] = "JS METHOD OK: generatePublicLink";
} else {
    $errors[] = "JS METHOD MISSING: generatePublicLink";
}

// 10. Check ui.js has shareInvoiceLink
$uiJs = file_get_contents(__DIR__ . '/frontend/js/ui.js');
if (strpos($uiJs, 'shareInvoiceLink') !== false) {
    $ok[] = "JS METHOD OK: shareInvoiceLink";
} else {
    $errors[] = "JS METHOD MISSING: shareInvoiceLink";
}

// 11. Check main.js has preview-share-btn listener
$mainJs = file_get_contents(__DIR__ . '/frontend/js/main.js');
if (strpos($mainJs, 'preview-share-btn') !== false) {
    $ok[] = "JS LISTENER OK: preview-share-btn";
} else {
    $errors[] = "JS LISTENER MISSING: preview-share-btn";
}

// 12. Check main.js has record-payment-btn listener
if (strpos($mainJs, 'record-payment-btn') !== false) {
    $ok[] = "JS LISTENER OK: record-payment-btn";
} else {
    $errors[] = "JS LISTENER MISSING: record-payment-btn";
}

// 13. Check main.js has save-client-btn listener
if (strpos($mainJs, 'save-client-btn') !== false) {
    $ok[] = "JS LISTENER OK: save-client-btn";
} else {
    $errors[] = "JS LISTENER MISSING: save-client-btn";
}

// 14. Check main.js has create-client-btn listener
if (strpos($mainJs, 'create-client-btn') !== false) {
    $ok[] = "JS LISTENER OK: create-client-btn";
} else {
    $errors[] = "JS LISTENER MISSING: create-client-btn";
}

// 15. Check main.js has save-payment-btn listener
if (strpos($mainJs, 'save-payment-btn') !== false) {
    $ok[] = "JS LISTENER OK: save-payment-btn";
} else {
    $errors[] = "JS LISTENER MISSING: save-payment-btn";
}

// 16. Check main.js has save-settings (business) listener
if (strpos($mainJs, 'save-settings') !== false || strpos($mainJs, 'settings-form') !== false) {
    $ok[] = "JS LISTENER OK: settings form save";
} else {
    $errors[] = "JS LISTENER MISSING: settings form save (no save-settings or settings-form submit)";
}

// Print results
echo "\n=== TEST RESULTS ===\n\n";
echo "ERRORS (" . count($errors) . "):\n";
foreach ($errors as $e) echo "  ✗ $e\n";
echo "\nOK (" . count($ok) . "):\n";
foreach ($ok as $o) echo "  ✓ $o\n";
