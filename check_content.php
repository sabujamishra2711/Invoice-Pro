<?php
$files = [
  'C:/xampp/htdocs/Customized/backend/controllers/SetupController.php',
  'C:/xampp/htdocs/Customized/backend/controllers/UserController.php',
  'C:/xampp/htdocs/Customized/backend/controllers/AuthController.php',
  'C:/xampp/htdocs/Customized/generate_setup_link.php',
  'C:/xampp/htdocs/Customized/frontend/login.html',
  'C:/xampp/htdocs/Customized/frontend/setup.html',
  'C:/xampp/htdocs/Customized/frontend/js/auth.js',
  'C:/xampp/htdocs/Customized/backend/api/router.php',
  'C:/xampp/htdocs/Customized/schema.sql',
];
foreach($files as $f) {
  echo "\n=== " . basename($f) . " ===\n";
  echo substr(file_get_contents($f), 0, 300) . "\n";
}
