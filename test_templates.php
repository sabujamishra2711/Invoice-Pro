<?php
// Test script to verify invoice template functionality
require_once 'backend/helpers/TemplateHelper.php';

header('Content-Type: application/json');

// Get all available templates
$templates = TemplateHelper::getAvailableTemplates();

// Return as JSON
echo json_encode([
    'success' => true,
    'templates' => $templates,
    'count' => count($templates),
    'message' => 'Successfully retrieved ' . count($templates) . ' invoice templates'
], JSON_PRETTY_PRINT);
