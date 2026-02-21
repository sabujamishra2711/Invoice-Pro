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

    public function getEmailSettings($input)
    {
        try {
            $userId = authenticateRequest();
            $db     = getDB();

            $stmt = $db->prepare("SELECT * FROM email_settings WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $emailSettings = $stmt->fetch();

            if (!$emailSettings) {
                $emailSettings = [
                    'smtp_host'       => '',
                    'smtp_port'       => 587,
                    'smtp_username'   => '',
                    'smtp_password'   => '',
                    'smtp_encryption' => 'tls',
                    'smtp_from_email' => '',
                    'smtp_from_name'  => '',
                ];
            } else {
                // Never expose the raw password back to the client
                $emailSettings['smtp_password'] = $emailSettings['smtp_password'] ? '••••••••' : '';
            }

            return ['success' => true, 'data' => ['email_settings' => $emailSettings]];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'EMAIL_SETTINGS_GET_FAILED', 'message' => 'Failed to fetch email settings', 'http_code' => 500];
        }
    }

    public function updateEmailSettings($input)
    {
        try {
            $userId = authenticateRequest();
            $db     = getDB();

            $host       = trim($input['smtp_host']       ?? '');
            $port       = (int)($input['smtp_port']      ?? 587);
            $username   = trim($input['smtp_username']   ?? '');
            $encryption = trim($input['smtp_encryption'] ?? 'tls');
            $fromEmail  = trim($input['smtp_from_email'] ?? '');
            $fromName   = trim($input['smtp_from_name']  ?? '');

            // Only update password if a real value was provided (not the masked placeholder)
            $newPassword = $input['smtp_password'] ?? '';
            $updatePassword = $newPassword !== '' && $newPassword !== '••••••••';

            // Check existing
            $stmt = $db->prepare("SELECT id, smtp_password FROM email_settings WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $password = $updatePassword ? $newPassword : $existing['smtp_password'];
                $stmt = $db->prepare("
                    UPDATE email_settings SET
                        smtp_host = ?, smtp_port = ?, smtp_username = ?,
                        smtp_password = ?, smtp_encryption = ?,
                        smtp_from_email = ?, smtp_from_name = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$host, $port, $username, $password, $encryption, $fromEmail, $fromName, $userId]);
            } else {
                $password = $updatePassword ? $newPassword : '';
                $stmt = $db->prepare("
                    INSERT INTO email_settings
                        (user_id, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_email, smtp_from_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $host, $port, $username, $password, $encryption, $fromEmail, $fromName]);
            }

            return ['success' => true, 'message' => 'Email settings saved successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'EMAIL_SETTINGS_UPDATE_FAILED', 'message' => 'Failed to save email settings: ' . $e->getMessage(), 'http_code' => 500];
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
