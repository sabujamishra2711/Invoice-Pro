<?php
$dirs = [
    __DIR__ . '/backend/controllers',
    __DIR__ . '/backend/services',
    __DIR__ . '/frontend/js',
];

$errors = [];
$ok = [];

foreach ($dirs as $dir) {
    $ext = (strpos($dir, 'js') !== false) ? '*.js' : '*.php';
    foreach (glob($dir . '/' . $ext) as $file) {
        if ($ext === '*.php') {
            $out = shell_exec('C:/xampp/php/php.exe -l "' . $file . '" 2>&1');
            if (strpos($out, 'No syntax errors') === false) {
                $errors[] = basename($file) . ': ' . trim($out);
            } else {
                $ok[] = basename($file);
            }
        }
    }
}

echo "=== PHP SYNTAX OK ===\n" . implode(', ', $ok) . "\n\n";
if ($errors) {
    echo "=== PHP ERRORS ===\n" . implode("\n", $errors) . "\n";
} else {
    echo "No PHP syntax errors found.\n";
}

// Now check JS for common bugs
echo "\n=== JS CHECKS ===\n";
$jsFiles = glob(__DIR__ . '/frontend/js/*.js');
foreach ($jsFiles as $file) {
    $code = file_get_contents($file);
    $name = basename($file);

    // Check for undefined function calls
    // Check api.js method names vs what main.js/ui.js call
    echo "Checking $name...\n";

    // Count braces
    $open = substr_count($code, '{');
    $close = substr_count($code, '}');
    if (abs($open - $close) > 3) {
        echo "  WARNING: Brace mismatch open=$open close=$close\n";
    }

    // Count parens
    $op = substr_count($code, '(');
    $cp = substr_count($code, ')');
    if (abs($op - $cp) > 3) {
        echo "  WARNING: Paren mismatch open=$op close=$cp\n";
    }
}
