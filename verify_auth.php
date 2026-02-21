<?php
$login   = file_get_contents('C:/xampp/htdocs/Customized/frontend/login.html');
$router  = file_get_contents('C:/xampp/htdocs/Customized/backend/api/router.php');
$schema  = file_get_contents('C:/xampp/htdocs/Customized/schema.sql');
$setup   = file_get_contents('C:/xampp/htdocs/Customized/backend/controllers/SetupController.php');
$userctl = file_get_contents('C:/xampp/htdocs/Customized/backend/controllers/UserController.php');

echo "=== login.html ===\n";
echo "signup tab present:   " . (strpos($login, 'form-signup') !== false   ? 'YES (BAD)' : 'NO (GOOD)') . "\n";
echo "create account tab:   " . (stripos($login, 'create account') !== false ? 'YES (BAD)' : 'NO (GOOD)') . "\n";
echo "sign in form present: " . (strpos($login, 'form-signin') !== false   ? 'YES (GOOD)' : 'NO (BAD)') . "\n";

echo "\n=== router.php ===\n";
echo "setup.validate route: " . (strpos($router, 'setup.validate') !== false ? 'YES' : 'MISSING') . "\n";
echo "setup.create route:   " . (strpos($router, 'setup.create')   !== false ? 'YES' : 'MISSING') . "\n";
echo "users.list route:     " . (strpos($router, 'users.list')     !== false ? 'YES' : 'MISSING') . "\n";
echo "users.add route:      " . (strpos($router, 'users.add')      !== false ? 'YES' : 'MISSING') . "\n";
echo "users.deactivate:     " . (strpos($router, 'users.deactivate') !== false ? 'YES' : 'MISSING') . "\n";

echo "\n=== schema.sql ===\n";
echo "setup_tokens table:   " . (strpos($schema, 'setup_tokens')   !== false ? 'YES' : 'MISSING') . "\n";
echo "role column in users: " . (strpos($schema, "'owner'")        !== false ? 'YES' : 'MISSING') . "\n";
echo "plan_subscriptions:   " . (strpos($schema, 'plan_subscriptions') !== false ? 'YES' : 'MISSING') . "\n";

echo "\n=== SetupController.php ===\n";
echo "validate method:      " . (strpos($setup, 'function validate')  !== false ? 'YES' : 'MISSING') . "\n";
echo "create method:        " . (strpos($setup, 'function create')    !== false ? 'YES' : 'MISSING') . "\n";
echo "token invalidation:   " . (strpos($setup, 'used_at')            !== false ? 'YES' : 'MISSING') . "\n";

echo "\n=== UserController.php ===\n";
echo "listUsers method:     " . (strpos($userctl, 'function listUsers')     !== false ? 'YES' : 'MISSING') . "\n";
echo "addUser method:       " . (strpos($userctl, 'function addUser')       !== false ? 'YES' : 'MISSING') . "\n";
echo "deactivateUser method:" . (strpos($userctl, 'function deactivate')    !== false ? 'YES' : 'MISSING') . "\n";
echo "owner-only guard:     " . (strpos($userctl, 'requireOwner')           !== false ? 'YES' : 'MISSING') . "\n";
