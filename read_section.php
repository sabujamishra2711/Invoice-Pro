<?php
$c = file_get_contents('C:/xampp/htdocs/invoice-management/frontend/index.html');
$pos = strpos($c, 'tab-account-settings');
echo 'tab-account-settings at: ' . $pos . PHP_EOL;
echo substr($c, $pos, 6000);
