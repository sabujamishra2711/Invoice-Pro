<?php
require_once 'config.php';

// Get user ID from Firebase token
$firebaseUID = getFirebaseUID();
$userId = getUserId($firebaseUID);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetSettings($userId);
        break;
    case 'POST':
        handleUpdateSettings($userId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGetSettings($userId)
{
    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();

        if (!$settings) {
            // Return default settings structure
            $settings = [
                'business_name' => '',
                'logo_path' => null,
                'address' => null,
                'gst_number' => null,
                'default_tax' => 18.00,
                'payment_terms' => null
            ];
        }

        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch settings: ' . $e->getMessage()]);
    }
}

function handleUpdateSettings($userId)
{
    $input = json_decode(file_get_contents('php://input'), true);

    try {
        $db = getDB();

        // Check if settings exist
        $stmt = $db->prepare("SELECT id FROM settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing settings
            $stmt = $db->prepare("
                UPDATE settings SET 
                    business_name = ?, 
                    address = ?, 
                    gst_number = ?, 
                    default_tax = ?, 
                    payment_terms = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $input['business_name'] ?? '',
                $input['address'] ?? null,
                $input['gst_number'] ?? null,
                $input['default_tax'] ?? 18.00,
                $input['payment_terms'] ?? null,
                $userId
            ]);
        } else {
            // Create new settings
            $stmt = $db->prepare("
                INSERT INTO settings (
                    user_id, business_name, address, gst_number, default_tax, payment_terms
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $input['business_name'] ?? '',
                $input['address'] ?? null,
                $input['gst_number'] ?? null,
                $input['default_tax'] ?? 18.00,
                $input['payment_terms'] ?? null
            ]);
        }

        // Return updated settings
        $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}
