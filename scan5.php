<?php
$api = file_get_contents(__DIR__ . '/frontend/js/api.js');
$router = file_get_contents(__DIR__ . '/backend/api/router.php');

// Find logout in api.js
preg_match('/logout[^}]{0,300}/s', $api, $m);
echo "=== logout in api.js ===\n" . ($m[0] ?? 'NOT FOUND') . "\n\n";

// Find auth routes in router
preg_match_all("/auth\.[^\s'\"]+/", $router, $rm);
echo "=== auth.* routes in router.php ===\n" . implode("\n", array_unique($rm[0])) . "\n\n";

// Also find what ui.js does for loadViewData (to check view routing)
$ui = file_get_contents(__DIR__ . '/frontend/js/ui.js');
preg_match('/loadViewData[^}]{0,500}/s', $ui, $ldm);
echo "=== loadViewData in ui.js ===\n" . ($ldm[0] ?? 'NOT FOUND') . "\n";
