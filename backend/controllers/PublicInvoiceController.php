<?php
// PublicInvoiceController – no auth required on public routes
// Handles: get invoice by token, create Razorpay order, verify & record payment

require_once __DIR__ . '/../services/InvoiceService.php';

class PublicInvoiceController
{
    // ── GET public.invoice.get?token=xxx ─────────────────────────────────────
    public function getByToken($input)
    {
        $token = trim($_GET['token'] ?? '');
        if (!$token) {
            return ['success' => false, 'error_code' => 'MISSING_TOKEN', 'message' => 'Token required', 'http_code' => 400];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare("
                SELECT
                    i.id, i.user_id, i.invoice_number, i.issue_date, i.due_date,
                    i.subtotal, i.tax_amount, i.total_amount, i.paid_amount,
                    i.currency, i.notes, i.status, i.public_token,
                    i.client_name_snapshot    AS client_name,
                    i.client_company_snapshot AS client_company,
                    i.client_email_snapshot   AS client_email,
                    i.business_name_snapshot  AS business_name,
                    i.business_address_snapshot AS business_address,
                    i.business_gst_snapshot   AS business_gst,
                    i.business_logo_path_snapshot AS logo_path
                FROM invoices i
                WHERE i.public_token = ? AND i.deleted_at IS NULL
            ");
            $stmt->execute([$token]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                return ['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Invoice not found', 'http_code' => 404];
            }

            // Items
            $stmt = $db->prepare("SELECT description, quantity, rate, tax_percent, line_total FROM invoice_items WHERE invoice_id = ? ORDER BY id");
            $stmt->execute([$invoice['id']]);
            $items = $stmt->fetchAll();

            // Payments already recorded
            $stmt = $db->prepare("SELECT amount, payment_date, method, reference, source FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
            $stmt->execute([$invoice['id']]);
            $payments = $stmt->fetchAll();

            // Dynamic status
            $paid   = (float)$invoice['paid_amount'];
            $total  = (float)$invoice['total_amount'];
            $due    = $invoice['due_date'];
            if ($paid >= $total)                                        $status = 'paid';
            elseif ($paid > 0)                                          $status = 'partial';
            elseif (strtotime($due) < strtotime('today'))               $status = 'overdue';
            else                                                        $status = 'sent';
            $invoice['calculated_status'] = $status;
            $invoice['balance']           = max(0, $total - $paid);

            // Use the invoice OWNER's payment settings (Razorpay + UPI)
            $rzpStmt = $db->prepare("SELECT razorpay_key_id, upi_id, upi_qr_path FROM settings WHERE user_id = ?");
            $rzpStmt->execute([$invoice['user_id']]);
            $ownerSettings = $rzpStmt->fetch();
            $rzpKeyId  = $ownerSettings['razorpay_key_id'] ?? null;
            $upiId     = $ownerSettings['upi_id']          ?? null;
            $upiQrPath = $ownerSettings['upi_qr_path']     ?? null;

            $invoice['razorpay_key_id'] = $rzpKeyId;
            $invoice['payment_enabled'] = !empty($rzpKeyId) && $invoice['balance'] > 0;
            $invoice['upi_id']          = $upiId;
            $invoice['upi_qr_url']      = $upiQrPath ? (defined('LOGO_PUBLIC_URL') ? LOGO_PUBLIC_URL . basename($upiQrPath) : '/invoice-management/backend/uploads/logos/' . basename($upiQrPath)) : null;

            return [
                'success' => true,
                'data'    => ['invoice' => $invoice, 'items' => $items, 'payments' => $payments]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'SERVER_ERROR', 'message' => 'Server error', 'http_code' => 500];
        }
    }

    // ── POST public.invoice.order?token=xxx ──────────────────────────────────
    // Creates a Razorpay order for the outstanding balance
    public function createOrder($input)
    {
        $token = trim($_GET['token'] ?? $input['token'] ?? '');
        if (!$token) {
            return ['success' => false, 'error_code' => 'MISSING_TOKEN', 'message' => 'Token required', 'http_code' => 400];
        }

            try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, user_id, invoice_number, total_amount, paid_amount, currency, deleted_at FROM invoices WHERE public_token = ? AND deleted_at IS NULL");
            $stmt->execute([$token]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                return ['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Invoice not found', 'http_code' => 404];
            }

            $balance = (float)$invoice['total_amount'] - (float)$invoice['paid_amount'];
            if ($balance <= 0) {
                return ['success' => false, 'error_code' => 'ALREADY_PAID', 'message' => 'Invoice is already fully paid', 'http_code' => 400];
            }

            // Look up the invoice OWNER's Razorpay credentials
            $rzpStmt = $db->prepare("SELECT razorpay_key_id, razorpay_key_secret FROM settings WHERE user_id = ?");
            $rzpStmt->execute([$invoice['user_id']]);
            $ownerRzp = $rzpStmt->fetch();
            $keyId     = $ownerRzp['razorpay_key_id']     ?? '';
            $keySecret = $ownerRzp['razorpay_key_secret']  ?? '';

            if (!$keyId || !$keySecret) {
                return ['success' => false, 'error_code' => 'PAYMENT_NOT_CONFIGURED', 'message' => 'The invoice owner has not configured online payments.', 'http_code' => 503];
            }

            // Razorpay works in paise (INR smallest unit). For other currencies use 100x.
            $amountMinor = (int)round($balance * 100);
            $currency    = strtoupper($invoice['currency'] ?? 'INR');

            $payload = [
                'amount'   => $amountMinor,
                'currency' => $currency,
                'receipt'  => 'pub_' . $invoice['id'] . '_' . time(),
                'notes'    => ['invoice_number' => $invoice['invoice_number'], 'token' => $token],
            ];

            $ch = curl_init('https://api.razorpay.com/v1/orders');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'error_code' => 'RAZORPAY_ERROR', 'message' => 'Could not create payment order', 'http_code' => 502];
            }

            $order = json_decode($resp, true);
            return [
                'success' => true,
                'data'    => [
                    'order_id'       => $order['id'],
                    'amount'         => $amountMinor,
                    'currency'       => $currency,
                    'key_id'         => $keyId,
                    'invoice_number' => $invoice['invoice_number'],
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'SERVER_ERROR', 'message' => 'Server error', 'http_code' => 500];
        }
    }

    // ── POST public.invoice.pay?token=xxx ────────────────────────────────────
    // Verifies Razorpay signature and records payment
    public function verifyAndPay($input)
    {
        $token = trim($_GET['token'] ?? $input['token'] ?? '');
        if (!$token) {
            return ['success' => false, 'error_code' => 'MISSING_TOKEN', 'message' => 'Token required', 'http_code' => 400];
        }

        $orderId   = $input['razorpay_order_id']  ?? '';
        $paymentId = $input['razorpay_payment_id'] ?? '';
        $signature = $input['razorpay_signature']  ?? '';

        if (!$orderId || !$paymentId || !$signature) {
            return ['success' => false, 'error_code' => 'MISSING_PARAMS', 'message' => 'Payment details incomplete', 'http_code' => 400];
        }

        try {
            $db   = getDB();

            // Fetch invoice and owner info first so we can use their key secret
            $stmt = $db->prepare("SELECT id, user_id, total_amount, paid_amount FROM invoices WHERE public_token = ? AND deleted_at IS NULL");
            $stmt->execute([$token]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                return ['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Invoice not found', 'http_code' => 404];
            }

            // Get the invoice owner's Razorpay secret for signature verification
            $rzpStmt = $db->prepare("SELECT razorpay_key_secret FROM settings WHERE user_id = ?");
            $rzpStmt->execute([$invoice['user_id']]);
            $ownerRzp  = $rzpStmt->fetch();
            $keySecret = $ownerRzp['razorpay_key_secret'] ?? '';

            // Verify Razorpay signature using owner's secret
            $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
            if (!hash_equals($expected, $signature)) {
                return ['success' => false, 'error_code' => 'INVALID_SIGNATURE', 'message' => 'Payment verification failed', 'http_code' => 400];
            }

            // Idempotency — check if this Razorpay payment_id was already recorded
            $dup = $db->prepare("SELECT id FROM payments WHERE online_payment_ref = ?");
            $dup->execute([$paymentId]);
            if ($dup->fetch()) {
                return ['success' => true, 'message' => 'Payment already recorded', 'data' => []];
            }

            $balance = (float)$invoice['total_amount'] - (float)$invoice['paid_amount'];
            if ($balance <= 0) {
                return ['success' => false, 'error_code' => 'ALREADY_PAID', 'message' => 'Invoice already fully paid', 'http_code' => 400];
            }

            $db->beginTransaction();

            // Record payment
            $stmt = $db->prepare("
                INSERT INTO payments (user_id, invoice_id, amount, payment_date, method, reference, online_payment_ref, source)
                SELECT user_id, id, ?, CURDATE(), 'online', ?, ?, 'online'
                FROM invoices WHERE id = ?
            ");
            $stmt->execute([$balance, $orderId, $paymentId, $invoice['id']]);

            // Update paid_amount on invoice
            $db->prepare("UPDATE invoices SET paid_amount = paid_amount + ? WHERE id = ?")->execute([$balance, $invoice['id']]);

            $db->commit();

            return ['success' => true, 'message' => 'Payment recorded successfully', 'data' => ['amount_paid' => $balance]];
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollback();
            return ['success' => false, 'error_code' => 'SERVER_ERROR', 'message' => 'Failed to record payment', 'http_code' => 500];
        }
    }

    // ── POST public.invoice.token.generate  (AUTHENTICATED) ──────────────────
    // Generates (or returns existing) public token for an invoice
    public function generateToken($input)
    {
        $userId    = authenticateRequest();
        $invoiceId = $_GET['id'] ?? $input['id'] ?? null;

        if (!$invoiceId) {
            return ['success' => false, 'error_code' => 'MISSING_ID', 'message' => 'Invoice ID required', 'http_code' => 400];
        }

        try {
            $db   = getDB();
            // Verify ownership
            $stmt = $db->prepare("SELECT id, public_token FROM invoices WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$invoiceId, $userId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                return ['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Invoice not found', 'http_code' => 404];
            }

            $token = $invoice['public_token'];
            if (!$token) {
                $token = bin2hex(random_bytes(24)); // 48-char hex token
                $db->prepare("UPDATE invoices SET public_token = ? WHERE id = ?")->execute([$token, $invoiceId]);
            }

            $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
            $publicUrl = $baseUrl . '/invoice-management/frontend/pay.html?token=' . $token;

            return [
                'success' => true,
                'data'    => ['token' => $token, 'url' => $publicUrl]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'SERVER_ERROR', 'message' => 'Failed to generate token', 'http_code' => 500];
        }
    }

    // ── DELETE public.invoice.token.revoke  (AUTHENTICATED) ─────────────────
    public function revokeToken($input)
    {
        $userId    = authenticateRequest();
        $invoiceId = $_GET['id'] ?? $input['id'] ?? null;

        if (!$invoiceId) {
            return ['success' => false, 'error_code' => 'MISSING_ID', 'message' => 'Invoice ID required', 'http_code' => 400];
        }

        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$invoiceId, $userId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Invoice not found', 'http_code' => 404];
            }
            $db->prepare("UPDATE invoices SET public_token = NULL WHERE id = ?")->execute([$invoiceId]);
            return ['success' => true, 'message' => 'Public link revoked'];
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'SERVER_ERROR', 'message' => 'Failed to revoke token', 'http_code' => 500];
        }
    }
}
