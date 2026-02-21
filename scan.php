<?php
$h = file_get_contents(__DIR__ . '/frontend/index.html');
preg_match_all('/id="([^"]+)"/', $h, $m);
$ids = array_unique($m[1]);
sort($ids);
echo "=== ALL IDs IN index.html ===\n";
foreach ($ids as $id) echo $id . "\n";

echo "\n=== BUTTONS with onclick or type=button/submit ===\n";
preg_match_all('/<button[^>]*>.*?<\/button>/si', $h, $btns);
foreach ($btns[0] as $btn) {
    if (preg_match('/id="([^"]+)"/', $btn, $idm)) {
        echo $idm[1] . "\n";
    }
}
