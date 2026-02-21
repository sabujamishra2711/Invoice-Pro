<?php
// Settings Management Controller

class SettingsController
{

    public function get($input)
    {
        try {
            $userId = authenticateRequest();

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
                    'payment_terms' => null,
                    'invoice_prefix' => 'INV',
                    'number_format' => 'YYYY-MM-NNNN'
                ];
            }

            return [
                'success' => true,
                'data' => ['settings' => $settings]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'SETTINGS_GET_FAILED',
                'message' => 'Failed to fetch settings',
                'http_code' => 500
            ];
        }
    }

    public function update($input)
    {
        try {
            $userId = authenticateRequest();

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
                        payment_terms = ?,
                        invoice_prefix = ?,
                        number_format = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $input['business_name'] ?? '',
                    $input['address'] ?? null,
                    $input['gst_number'] ?? null,
                    $input['default_tax'] ?? 18.00,
                    $input['payment_terms'] ?? null,
                    $input['invoice_prefix'] ?? 'INV',
                    $input['number_format'] ?? 'YYYY-MM-NNNN',
                    $userId
                ]);
            } else {
                // Create new settings
                $stmt = $db->prepare("
                    INSERT INTO settings (
                        user_id, business_name, address, gst_number, default_tax, payment_terms, invoice_prefix, number_format
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $input['business_name'] ?? '',
                    $input['address'] ?? null,
                    $input['gst_number'] ?? null,
                    $input['default_tax'] ?? 18.00,
                    $input['payment_terms'] ?? null,
                    $input['invoice_prefix'] ?? 'INV',
                    $input['number_format'] ?? 'YYYY-MM-NNNN'
                ]);
            }

            // Return updated settings
            $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $settings = $stmt->fetch();

            return [
                'success' => true,
                'data' => ['settings' => $settings],
                'message' => 'Settings updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'SETTINGS_UPDATE_FAILED',
                'message' => 'Failed to update settings: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }
}
