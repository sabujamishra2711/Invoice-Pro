<?php
/**
 * Outputs Firebase public config as a JS snippet.
 * Served as: /invoice-management/backend/api/firebase-config.js
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');

// Read from Orchids-injected env vars (available via Apache SetEnv or system env)
$apiKey     = getenv('FIREBASE_API_KEY')     ?: '';
$authDomain = getenv('FIREBASE_AUTH_DOMAIN') ?: '';
$projectId  = getenv('FIREBASE_PROJECT_ID')  ?: '';
$appId      = getenv('FIREBASE_APP_ID')      ?: '';

// Also try $_ENV and $_SERVER for different SAPI environments
if (!$apiKey)     $apiKey     = $_ENV['FIREBASE_API_KEY']     ?? $_SERVER['FIREBASE_API_KEY']     ?? '';
if (!$authDomain) $authDomain = $_ENV['FIREBASE_AUTH_DOMAIN'] ?? $_SERVER['FIREBASE_AUTH_DOMAIN'] ?? '';
if (!$projectId)  $projectId  = $_ENV['FIREBASE_PROJECT_ID']  ?? $_SERVER['FIREBASE_PROJECT_ID']  ?? '';
if (!$appId)      $appId      = $_ENV['FIREBASE_APP_ID']      ?? $_SERVER['FIREBASE_APP_ID']      ?? '';

echo "window.FIREBASE_CONFIG = " . json_encode([
    'apiKey'     => $apiKey,
    'authDomain' => $authDomain,
    'projectId'  => $projectId,
    'appId'      => $appId,
], JSON_UNESCAPED_SLASHES) . ";\n";
