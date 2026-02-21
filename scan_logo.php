<?php
$ui = file_get_contents('C:/xampp/htdocs/invoice-management/frontend/js/ui.js');
$lines = explode("\n", $ui);
foreach ($lines as $i => $line) {
    if (stripos($line, 'business_logo') !== false || stripos($line, 'logo_url') !== false || stripos($line, 'previewInvoice') !== false || stripos($line, 'logo') !== false) {
        echo ($i+1) . ': ' . trim($line) . "\n";
    }
}
