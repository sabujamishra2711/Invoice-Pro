<?php
$js = file_get_contents(__DIR__ . '/frontend/js/main.js');

// Find all getElementById + addEventListener patterns
preg_match_all("/getElementById\('([^']+)'\)/", $js, $m1);
preg_match_all('/getElementById\("([^"]+)"\)/', $js, $m2);
preg_match_all("/querySelector\('([^']+)'\)/", $js, $m3);
preg_match_all('/querySelector\("([^"]+)"\)/', $js, $m4);

$listened = array_unique(array_merge($m1[1], $m2[1]));
sort($listened);

echo "=== IDs referenced in main.js ===\n";
foreach ($listened as $id) echo $id . "\n";

// Now compare with button IDs from HTML
$html = file_get_contents(__DIR__ . '/frontend/index.html');
preg_match_all('/<button[^>]*id="([^"]+)"[^>]*>/si', $html, $bm);
$btnIds = array_unique($bm[1]);
sort($btnIds);

echo "\n=== BUTTONS in HTML not handled in main.js ===\n";
foreach ($btnIds as $id) {
    if (!in_array($id, $listened)) {
        echo "MISSING: $id\n";
    }
}
