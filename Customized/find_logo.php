<?php
$html = file_get_contents('C:/xampp/htdocs/invoice-management/frontend/index.html');
$lines = explode("\n", $html);
$start = strpos($html, '<label class="form-label">Business Logo</label>');
// find line number
$before = substr($html, 0, $start);
$line = substr_count($before, "\n") + 1;
echo "Line number: $line\n";
echo implode("\n", array_slice($lines, $line - 1, 20));
