<?php
$h = file_get_contents(__DIR__ . '/frontend/index.html');
// Find the stat card with expense-billable
preg_match('/.{0,200}id="expense-billable".{0,200}/s', $h, $m);
echo $m[0] ?? 'NOT FOUND';
