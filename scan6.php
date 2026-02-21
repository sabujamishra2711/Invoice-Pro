<?php
$ui = file_get_contents(__DIR__ . '/frontend/js/ui.js');
$main = file_get_contents(__DIR__ . '/frontend/js/main.js');
$expense = file_get_contents(__DIR__ . '/frontend/js/expense.js');
$recurring = file_get_contents(__DIR__ . '/frontend/js/recurring.js');
$api = file_get_contents(__DIR__ . '/frontend/js/api.js');

// Get all method-like definitions in ui.js (4-space indent = class method)
preg_match_all('/^    (?:async )?(\w+)\s*\(/m', $ui, $m);
$uiMethods = array_unique($m[1]);
sort($uiMethods);
echo "=== UI METHODS (" . count($uiMethods) . ") ===\n";
echo implode(', ', $uiMethods) . "\n\n";

// Find all ui.METHOD() calls in main.js
preg_match_all('/(?:uiManager|ui)\.(\w+)\s*\(/', $main . $expense . $recurring, $calls);
$calledMethods = array_unique($calls[1]);
sort($calledMethods);

echo "=== MISSING ui.* METHODS ===\n";
$missing = [];
foreach ($calledMethods as $m) {
    if (!in_array($m, $uiMethods)) {
        $missing[] = $m;
        echo "  MISSING: $m\n";
    }
}
if (!$missing) echo "  None missing\n";

// Check for uiManager properties accessed
preg_match_all('/uiManager\.(\w+)(?!\s*\()/', $main . $expense . $recurring, $props);
$calledProps = array_unique($props[1]);
echo "\n=== UI PROPERTIES used externally ===\n";
foreach ($calledProps as $p) {
    echo "  $p\n";
}

// Now read full ui.js to check loadViewData switch cases
echo "\n=== loadViewData SWITCH CASES ===\n";
preg_match('/loadViewData[^{]+\{(.+?)(?=\n    \})/s', $ui, $lm);
echo $lm[0] ?? 'NOT FOUND';
