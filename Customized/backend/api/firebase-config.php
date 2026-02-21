<?php
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../config.php';

echo "window.FIREBASE_CONFIG = " . json_encode([
    'apiKey'     => FIREBASE_API_KEY,
    'authDomain' => FIREBASE_AUTH_DOMAIN,
    'projectId'  => FIREBASE_PROJECT_ID,
    'appId'      => FIREBASE_APP_ID,
], JSON_UNESCAPED_SLASHES) . ";\n";
