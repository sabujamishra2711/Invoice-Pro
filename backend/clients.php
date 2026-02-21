<?php
require_once 'config.php';

// Get user ID from Firebase token
$firebaseUID = getFirebaseUID();
$userId = getUserId($firebaseUID);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetClients($userId);
        break;
    case 'POST':
        handleCreateClient($userId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGetClients($userId)
{
    try {
        $db = getDB();

        // Get all clients for user
        $stmt = $db->prepare("
            SELECT 
                c.*,
                COALESCE(SUM(i.total_amount), 0) as total_billed,
                COALESCE(SUM(i.paid_amount), 0) as total_paid,
                COALESCE(SUM(i.total_amount - i.paid_amount), 0) as outstanding
            FROM clients c
            LEFT JOIN invoices i ON c.id = i.client_id AND i.user_id = ?
            WHERE c.user_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        $clients = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'clients' => $clients
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch clients: ' . $e->getMessage()]);
    }
}

function handleCreateClient($userId)
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (!isset($input['name']) || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Client name is required']);
        return;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO clients (user_id, name, email, phone, company, address, gst_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $input['name'],
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['company'] ?? null,
            $input['address'] ?? null,
            $input['gst_number'] ?? null
        ]);

        $clientId = $db->lastInsertId();

        // Return the created client
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'client' => $client
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create client: ' . $e->getMessage()]);
    }
}
