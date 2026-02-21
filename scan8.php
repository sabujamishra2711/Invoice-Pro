<?php
$ui = file_get_contents(__DIR__ . '/frontend/js/ui.js');

// Find saveClient method
preg_match('/async saveClient\(\)[^{]*\{(.+?)(?=\n    (?:async )?[a-zA-Z_])/s', $ui, $m);
echo "=== saveClient ===\n" . substr($m[0] ?? 'NOT FOUND', 0, 1500) . "\n\n";

// Find showClientModal
preg_match('/showClientModal\([^)]*\)[^{]*\{(.+?)(?=\n    (?:async )?[a-zA-Z_])/s', $ui, $m2);
echo "=== showClientModal ===\n" . substr($m2[0] ?? 'NOT FOUND', 0, 1500) . "\n\n";

// Find saveInvoice
preg_match('/async saveInvoice\(\)[^{]*\{(.+?)(?=\n    (?:async )?[a-zA-Z_])/s', $ui, $m3);
echo "=== saveInvoice ===\n" . substr($m3[0] ?? 'NOT FOUND', 0, 2000) . "\n\n";

// Find loadClients - check what client data structure it expects
preg_match('/async loadClients\(\)[^{]*\{(.+?)(?=\n    (?:async )?[a-zA-Z_])/s', $ui, $m4);
echo "=== loadClients ===\n" . substr($m4[0] ?? 'NOT FOUND', 0, 1000) . "\n\n";

// Find how settings.update is called
preg_match('/async saveSettings\(\)[^{]*\{(.+?)(?=\n    (?:async )?[a-zA-Z_])/s', $ui, $m5);
echo "=== saveSettings ===\n" . substr($m5[0] ?? 'NOT FOUND', 0, 1500) . "\n\n";
