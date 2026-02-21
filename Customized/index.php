<?php
// Frontend entry point that serves the SPA
// This file should be placed in your XAMPP htdocs directory

// Set the correct content type
header('Content-Type: text/html; charset=UTF-8');

// Get the requested path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Debug: log the raw path
error_log("Raw URI path: " . $_SERVER['REQUEST_URI']);
error_log("Parsed path: " . $path);

// Remove the leading slash
$path = ltrim($path, '/');

// Remove 'invoice-management/' if it's at the beginning of the path
if (strpos($path, 'invoice-management/') === 0) {
    $path = substr($path, strlen('invoice-management/'));
    error_log("After removing 'invoice-management/': " . $path);
}

// Route API requests to the backend
if (strpos($path, 'invoice-api/') === 0) {
    // Remove 'invoice-api/' from the path
    $apiPath = substr($path, strlen('invoice-api/'));

    // Include the backend API
    $_GET['route'] = $apiPath;
    require_once __DIR__ . '/backend/api/index.php';
    exit;
}

// For all other requests, serve the frontend SPA
// This handles client-side routing
$frontendPath = __DIR__ . '/frontend/' . $path;

// Normalize the path to prevent directory traversal
$normalizedPath = realpath(__DIR__ . '/frontend/' . $path);
$expectedPrefix = realpath(__DIR__ . '/frontend');

// Ensure the resolved path is within the frontend directory
if ($normalizedPath && $expectedPrefix && strpos($normalizedPath, $expectedPrefix) === 0) {
    $frontendPath = $normalizedPath;
} else {
    error_log("Security: Path outside frontend directory, requested: " . $path);
}

// Debug: log what path is being requested and resolved
error_log("Final request path: " . $path);
error_log("Resolved frontend path: " . $frontendPath);
error_log("Normalized path: " . ($normalizedPath ?? 'null'));
error_log("Expected prefix: " . ($expectedPrefix ?? 'null'));
error_log("File exists: " . (file_exists($frontendPath) ? 'YES' : 'NO'));
error_log("Is file: " . (is_file($frontendPath) ? 'YES' : 'NO'));

// If the file exists, serve it directly
if (file_exists($frontendPath) && is_file($frontendPath)) {
    // Set appropriate content type based on file extension
    $extension = pathinfo($frontendPath, PATHINFO_EXTENSION);
    $contentTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml'
    ];

    if (isset($contentTypes[$extension])) {
        header('Content-Type: ' . $contentTypes[$extension]);
    }

    readfile($frontendPath);
    exit;
}

// For SPA routing, serve the main index.html
// This allows client-side routing to work properly
$indexPath = __DIR__ . '/frontend/index.html';
if (file_exists($indexPath)) {
    header('Content-Type: text/html');
    readfile($indexPath);
} else {
    http_response_code(404);
    echo 'Frontend not found';
}
