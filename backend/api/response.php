<?php
// Standardized Response Format

// Handle preflight requests (OPTIONS) immediately with CORS headers
// This must be done BEFORE any other output
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *', false);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', false);
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With', false);
        header('Access-Control-Max-Age: 86400', false);
    }
    http_response_code(200);
    exit();
}

// Function to send CORS headers for all responses
function sendCORSHeaders()
{
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *', false);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS', false);
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With', false);
        header('Access-Control-Max-Age: 86400', false);
    }
}

// Success response format
function sendSuccess($data = [], $message = 'Success', $httpCode = 200)
{
    sendCORSHeaders();
    http_response_code($httpCode);
    header('Content-Type: application/json');

    $response = [
        'success' => true,
        'data' => $data,
        'message' => $message
    ];

    echo json_encode($response);
    exit();
}

// Error response format
function sendError($errorCode, $message, $httpCode = 400)
{
    sendCORSHeaders();
    http_response_code($httpCode);
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'error' => [
            'code' => $errorCode,
            'message' => $message
        ]
    ];

    echo json_encode($response);
    exit();
}

// Validation error response
function sendValidationError($errors)
{
    sendCORSHeaders();
    http_response_code(400);
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Validation failed',
            'details' => $errors
        ]
    ];

    echo json_encode($response);
    exit();
}

// Unauthorized response
function sendUnauthorized($message = 'Unauthorized')
{
    sendError('UNAUTHORIZED', $message, 401);
}

// Not found response
function sendNotFound($message = 'Resource not found')
{
    sendError('NOT_FOUND', $message, 404);
}

// Internal server error response
function sendInternalServerError($message = 'Internal server error')
{
    sendError('INTERNAL_ERROR', $message, 500);
}
