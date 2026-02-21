<?php
require_once 'config.php';

// Get user ID from Firebase token
$firebaseUID = getFirebaseUID();
$userId = getUserId($firebaseUID);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get invoice ID from URL parameter
$invoiceId = $_GET['id'] ?? null;

if (!$invoiceId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invoice ID is required']);
    exit();
}

try {
    $db = getDB();

    // Get invoice details with client info
    $stmt = $db->prepare("
        SELECT 
            i.*,
            c.name as client_name,
            c.company as client_company,
            c.email as client_email,
            c.phone as client_phone,
            c.address as client_address,
            c.gst_number as client_gst,
            u.name as user_name,
            u.email as user_email,
            s.business_name,
            s.address as business_address,
            s.gst_number as business_gst,
            s.default_tax
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN settings s ON u.id = s.user_id
        WHERE i.id = ? AND i.user_id = ?
    ");
    $stmt->execute([$invoiceId, $userId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit();
    }

    // Get invoice items
    $stmt = $db->prepare("
        SELECT * FROM invoice_items 
        WHERE invoice_id = ?
        ORDER BY id
    ");
    $stmt->execute([$invoiceId]);
    $items = $stmt->fetchAll();

    // Get payments
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE invoice_id = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$invoiceId]);
    $payments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'invoice' => $invoice,
        'items' => $items,
        'payments' => $payments
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch invoice details: ' . $e->getMessage()]);
}
