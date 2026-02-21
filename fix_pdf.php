<?php
$lines = file('C:/xampp/htdocs/invoice-management/backend/services/PDFService.php');
// Keep only lines 1-1625 (0-indexed: 0-1624) then close class
$keep = array_slice($lines, 0, 1625);
$content = implode('', $keep);
$content = rtrim($content) . "\n}\n";
file_put_contents('C:/xampp/htdocs/invoice-management/backend/services/PDFService.php', $content);
echo "Done. Lines written: " . count($keep) . "\n";
