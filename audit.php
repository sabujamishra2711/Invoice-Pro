<?php
error_reporting(0);

$api    = file_get_contents(__DIR__ . '/frontend/js/api.js');
$main   = file_get_contents(__DIR__ . '/frontend/js/main.js');
$ui     = file_get_contents(__DIR__ . '/frontend/js/ui.js');
$exp    = file_get_contents(__DIR__ . '/frontend/js/expense.js');
$rec    = file_get_contents(__DIR__ . '/frontend/js/recurring.js');
$router = file_get_contents(__DIR__ . '/backend/api/router.php');

// 1. Get all API method names defined in api.js
preg_match_all('/async\s+(\w+)\s*\(/', $api, $m);
$apiMethods = array_unique($m[1]);
sort($apiMethods);
echo "=== API METHODS DEFINED (" . count($apiMethods) . ") ===\n";
echo implode(', ', $apiMethods) . "\n\n";

// 2. Find all api.METHOD() calls across all JS
$allJs = $main . $ui . $exp . $rec;
preg_match_all('/api\.(\w+)\s*\(/', $allJs, $calls);
$called = array_unique($calls[1]);
sort($called);
echo "=== API METHODS CALLED ===\n";
$missing = [];
foreach ($called as $m) {
    if (!in_array($m, $apiMethods)) {
        $missing[] = $m;
        echo "  MISSING IN api.js: api.$m()\n";
    }
}
if (!$missing) echo "  All called methods exist in api.js\n";

// 3. Routes in router.php vs routes called in api.js
preg_match_all("/'([a-z][a-z0-9._]+)'\s*=>/", $router, $rm);
$routes = array_unique($rm[1]);
sort($routes);
echo "\n=== ROUTER ROUTES (" . count($routes) . ") ===\n";
echo implode(', ', $routes) . "\n";

preg_match_all("/'([a-z][a-z0-9._]+)'/", $api, $am);
$apiRoutes = array_unique(array_filter($am[1], fn($r) => strpos($r, '.') !== false));
sort($apiRoutes);
echo "\n=== ROUTES CALLED IN api.js (" . count($apiRoutes) . ") ===\n";
$missingRoutes = [];
foreach ($apiRoutes as $r) {
    if (!in_array($r, $routes)) {
        $missingRoutes[] = $r;
        echo "  MISSING IN router.php: '$r'\n";
    }
}
if (!$missingRoutes) echo "  All routes exist in router.php\n";

// 4. Check ui.* calls in main.js
preg_match_all('/ui\.(\w+)\s*\(/', $main, $uicalls);
$calledUi = array_unique($uicalls[1]);
preg_match_all('/(\w+)\s*\([^)]*\)\s*\{/', $ui, $uiDef);
preg_match_all('/async\s+(\w+)\s*\(/', $ui, $uiDefAsync);
$uiMethods = array_unique(array_merge($uiDef[1], $uiDefAsync[1]));
echo "\n=== ui.* calls in main.js missing from ui.js ===\n";
$any = false;
foreach ($calledUi as $m) {
    if (!in_array($m, $uiMethods)) {
        echo "  MISSING: ui.$m()\n";
        $any = true;
    }
}
if (!$any) echo "  All ui.* calls exist\n";

// 5. Check expenseManager.* calls
preg_match_all('/expenseManager\.(\w+)\s*\(/', $main . $ui, $expcalls);
$calledExp = array_unique($expcalls[1]);
preg_match_all('/(\w+)\s*\(/', $exp, $expDef);
$expMethods = array_unique($expDef[1]);
echo "\n=== expenseManager.* calls missing from expense.js ===\n";
$any = false;
foreach ($calledExp as $m) {
    if (!in_array($m, $expMethods)) {
        echo "  MISSING: expenseManager.$m()\n";
        $any = true;
    }
}
if (!$any) echo "  All expenseManager.* calls exist\n";

// 6. Check recurringManager.* calls
preg_match_all('/recurringManager\.(\w+)\s*\(/', $main . $ui, $reccalls);
$calledRec = array_unique($reccalls[1]);
preg_match_all('/(\w+)\s*\(/', $rec, $recDef);
$recMethods = array_unique($recDef[1]);
echo "\n=== recurringManager.* calls missing from recurring.js ===\n";
$any = false;
foreach ($calledRec as $m) {
    if (!in_array($m, $recMethods)) {
        echo "  MISSING: recurringManager.$m()\n";
        $any = true;
    }
}
if (!$any) echo "  All recurringManager.* calls exist\n";

echo "\nDone.\n";
