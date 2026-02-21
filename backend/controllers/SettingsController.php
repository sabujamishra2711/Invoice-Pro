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
                $settings = [
                    'business_name'       => '',
                    'logo_path'           => null,
                    'logo_url'            => null,
                    'address'             => null,
                    'gst_number'          => null,
                    'default_tax'         => 18.00,
                    'payment_terms'       => null,
                    'invoice_prefix'      => 'INV',
                    'number_format'       => 'YYYY-MM-NNNN',
                    'razorpay_key_id'     => null,
                    'razorpay_key_secret' => null,
                    'upi_id'              => null,
                    'upi_qr_path'         => null,
                    'upi_qr_url'          => null,
                ];
            } else {
                // Build a publicly accessible URL for the logo
                $settings['logo_url'] = !empty($settings['logo_path'])
                    ? LOGO_PUBLIC_URL . basename($settings['logo_path'])
                    : null;
                // Build UPI QR URL
                $settings['upi_qr_url'] = !empty($settings['upi_qr_path'])
                    ? LOGO_PUBLIC_URL . basename($settings['upi_qr_path'])
                    : null;
                // Never expose the raw secret to the client
                if (!empty($settings['razorpay_key_secret'])) {
                    $settings['razorpay_key_secret'] = '••••••••';
                }
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

    public function uploadLogo($input)
    {
        try {
            $userId = authenticateRequest();

            if (empty($_FILES['logo'])) {
                return ['success' => false, 'error_code' => 'NO_FILE', 'message' => 'No file uploaded.', 'http_code' => 400];
            }

            $file = $_FILES['logo'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error_code' => 'UPLOAD_ERROR', 'message' => 'File upload failed.', 'http_code' => 400];
            }

            // Validate MIME type
            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMime, true)) {
                return ['success' => false, 'error_code' => 'INVALID_TYPE', 'message' => 'Only JPG, PNG, GIF, WebP, SVG allowed.', 'http_code' => 400];
            }

            // Max 2 MB
            if ($file['size'] > 2 * 1024 * 1024) {
                return ['success' => false, 'error_code' => 'FILE_TOO_LARGE', 'message' => 'Logo must be under 2 MB.', 'http_code' => 400];
            }

            // Derive safe extension from MIME
            $extMap = [
                'image/jpeg'  => 'jpg',
                'image/png'   => 'png',
                'image/gif'   => 'gif',
                'image/webp'  => 'webp',
                'image/svg+xml' => 'svg',
            ];
            $ext = $extMap[$mime];

            // Delete old logo if exists
            $db   = getDB();
            $stmt = $db->prepare("SELECT logo_path FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();
            if ($existing && !empty($existing['logo_path'])) {
                $old = LOGO_STORAGE_PATH . basename($existing['logo_path']);
                if (file_exists($old)) @unlink($old);
            }

            // Save new file
            $filename = 'logo_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = LOGO_STORAGE_PATH . $filename;

            if (!is_dir(LOGO_STORAGE_PATH)) {
                mkdir(LOGO_STORAGE_PATH, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                return ['success' => false, 'error_code' => 'SAVE_FAILED', 'message' => 'Could not save logo.', 'http_code' => 500];
            }

              // Persist path in DB — upsert to prevent duplicate rows
            $stmt = $db->prepare("
                INSERT INTO settings (user_id, business_name, logo_path) VALUES (?, '', ?)
                ON DUPLICATE KEY UPDATE logo_path = VALUES(logo_path)
            ");
            $stmt->execute([$userId, $filename]);

            $logoUrl = LOGO_PUBLIC_URL . $filename;

            return [
                'success' => true,
                'message' => 'Logo uploaded successfully.',
                'data'    => ['logo_url' => $logoUrl, 'logo_path' => $filename]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'LOGO_UPLOAD_FAILED', 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function deleteLogo($input)
    {
        try {
            $userId = authenticateRequest();

            $db   = getDB();
            $stmt = $db->prepare("SELECT logo_path FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $row  = $stmt->fetch();

            if ($row && !empty($row['logo_path'])) {
                $file = LOGO_STORAGE_PATH . basename($row['logo_path']);
                if (file_exists($file)) @unlink($file);

                $stmt = $db->prepare("UPDATE settings SET logo_path = NULL WHERE user_id = ?");
                $stmt->execute([$userId]);
            }

            return ['success' => true, 'message' => 'Logo removed.'];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'LOGO_DELETE_FAILED', 'message' => $e->getMessage(), 'http_code' => 500];
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

    public function uploadUpiQr($input)
    {
        try {
            $userId = authenticateRequest();

            if (empty($_FILES['upi_qr'])) {
                return ['success' => false, 'error_code' => 'NO_FILE', 'message' => 'No file uploaded.', 'http_code' => 400];
            }

            $file = $_FILES['upi_qr'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'error_code' => 'UPLOAD_ERROR', 'message' => 'File upload failed.', 'http_code' => 400];
            }

            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, $allowedMime, true)) {
                return ['success' => false, 'error_code' => 'INVALID_TYPE', 'message' => 'Only JPG, PNG, GIF, WebP allowed.', 'http_code' => 400];
            }

            if ($file['size'] > 2 * 1024 * 1024) {
                return ['success' => false, 'error_code' => 'FILE_TOO_LARGE', 'message' => 'Image must be under 2 MB.', 'http_code' => 400];
            }

            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $ext = $extMap[$mime];

            $db   = getDB();
            $stmt = $db->prepare("SELECT upi_qr_path FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();

            // Delete old QR if present
            if ($existing && !empty($existing['upi_qr_path'])) {
                $old = LOGO_STORAGE_PATH . basename($existing['upi_qr_path']);
                if (file_exists($old)) @unlink($old);
            }

            $filename = 'upi_qr_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = LOGO_STORAGE_PATH . $filename;

            if (!is_dir(LOGO_STORAGE_PATH)) mkdir(LOGO_STORAGE_PATH, 0755, true);

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                return ['success' => false, 'error_code' => 'SAVE_FAILED', 'message' => 'Could not save image.', 'http_code' => 500];
            }

            // Upsert — prevents duplicate rows
            $db->prepare("
                INSERT INTO settings (user_id, business_name, upi_qr_path) VALUES (?, '', ?)
                ON DUPLICATE KEY UPDATE upi_qr_path = VALUES(upi_qr_path)
            ")->execute([$userId, $filename]);

            return [
                'success' => true,
                'message' => 'UPI QR uploaded successfully.',
                'data'    => ['upi_qr_url' => LOGO_PUBLIC_URL . $filename, 'upi_qr_path' => $filename]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'UPI_QR_UPLOAD_FAILED', 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function deleteUpiQr($input)
    {
        try {
            $userId = authenticateRequest();
            $db     = getDB();
            $stmt   = $db->prepare("SELECT upi_qr_path FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row && !empty($row['upi_qr_path'])) {
                $file = LOGO_STORAGE_PATH . basename($row['upi_qr_path']);
                if (file_exists($file)) @unlink($file);
                $db->prepare("UPDATE settings SET upi_qr_path = NULL WHERE user_id = ?")->execute([$userId]);
            }

            return ['success' => true, 'message' => 'UPI QR removed.'];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'UPI_QR_DELETE_FAILED', 'message' => $e->getMessage(), 'http_code' => 500];
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

            // Preserve existing Razorpay secret if the client sent back the masked placeholder
            $newRzpSecret    = $input['razorpay_key_secret'] ?? '';
            $updateRzpSecret = $newRzpSecret !== '' && $newRzpSecret !== '••••••••';

            if ($updateRzpSecret) {
                $rzpSecret = $newRzpSecret;
            } else {
                $row = $db->prepare("SELECT razorpay_key_secret FROM settings WHERE user_id = ?");
                $row->execute([$userId]);
                $r = $row->fetch();
                $rzpSecret = $r['razorpay_key_secret'] ?? null;
            }

            // Single upsert — impossible to create a duplicate row
            $stmt = $db->prepare("
                INSERT INTO settings
                    (user_id, business_name, address, gst_number, default_tax, payment_terms,
                     invoice_prefix, number_format, razorpay_key_id, razorpay_key_secret, upi_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    business_name       = VALUES(business_name),
                    address             = VALUES(address),
                    gst_number          = VALUES(gst_number),
                    default_tax         = VALUES(default_tax),
                    payment_terms       = VALUES(payment_terms),
                    invoice_prefix      = VALUES(invoice_prefix),
                    number_format       = VALUES(number_format),
                    razorpay_key_id     = VALUES(razorpay_key_id),
                    razorpay_key_secret = VALUES(razorpay_key_secret),
                    upi_id              = VALUES(upi_id)
            ");
            $stmt->execute([
                $userId,
                $input['business_name'] ?? '',
                $input['address'] ?? null,
                $input['gst_number'] ?? null,
                $input['default_tax'] ?? 18.00,
                $input['payment_terms'] ?? null,
                $input['invoice_prefix'] ?? 'INV',
                $input['number_format'] ?? 'YYYY-MM-NNNN',
                $input['razorpay_key_id'] ?: null,
                $rzpSecret ?: null,
                $input['upi_id'] ?: null,
            ]);

            // Return updated settings (mask the secret)
            $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $settings = $stmt->fetch();
            if (!empty($settings['razorpay_key_secret'])) {
                $settings['razorpay_key_secret'] = '••••••••';
            }
            $settings['upi_qr_url'] = !empty($settings['upi_qr_path'])
                ? LOGO_PUBLIC_URL . basename($settings['upi_qr_path'])
                : null;

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
