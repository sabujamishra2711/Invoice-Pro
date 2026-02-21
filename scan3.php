<?php
$files = [
    'expense.js' => file_get_contents(__DIR__ . '/frontend/js/expense.js'),
    'recurring.js' => file_get_contents(__DIR__ . '/frontend/js/recurring.js'),
    'ui.js' => file_get_contents(__DIR__ . '/frontend/js/ui.js'),
    'main.js' => file_get_contents(__DIR__ . '/frontend/js/main.js'),
    'invoices.js' => file_get_contents(__DIR__ . '/frontend/js/invoices.js'),
];

$missing = ['confirm-modal-action','create-expense-btn','create-recurring-btn','expense-cancel-btn',
    'expense-clear-filter-btn','expense-filter-btn','expense-save-btn','recurring-add-item',
    'recurring-cancel-btn','recurring-save-btn','upg-calc-back','upg-close-btn',
    'upg-configure-enterprise','upg-pay-enterprise','upg-pay-professional'];

foreach ($missing as $id) {
    $found = [];
    foreach ($files as $name => $code) {
        if (strpos($code, $id) !== false) $found[] = $name;
    }
    echo ($found ? "OK    [$id] in: " . implode(', ', $found) : "MISSING [$id]") . "\n";
}
