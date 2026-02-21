<?php
// Email Controller — sends invoices via SMTP

require_once __DIR__ . '/../services/SmtpMailer.php';
require_once __DIR__ . '/../services/InvoiceService.php';

class EmailController
{
    /**
     * POST invoice.email.send
     * Body: { invoice_id, to_email, to_name, subject, message, attach_pdf }
     */
    public function send($input)
    {
        try {
            $userId = authenticateRequest();

            $invoiceId = $input['invoice_id'] ?? null;
            $toEmail   = trim($input['to_email'] ?? '');
            $toName    = trim($input['to_name']  ?? '');
            $subject   = trim($input['subject']  ?? '');
            $message   = trim($input['message']  ?? '');
            $attachPdf = !empty($input['attach_pdf']);

            if (!$invoiceId) {
                return ['success' => false, 'error_code' => 'VALIDATION_ERROR', 'message' => 'Invoice ID required', 'http_code' => 400];
            }
            if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error_code' => 'VALIDATION_ERROR', 'message' => 'Valid recipient email required', 'http_code' => 400];
            }
            if (!$subject) {
                return ['success' => false, 'error_code' => 'VALIDATION_ERROR', 'message' => 'Subject is required', 'http_code' => 400];
            }

            // Load invoice
            $invoiceService = new InvoiceService();
            $invoice = $invoiceService->getInvoiceWithDetails($invoiceId, $userId);
            if (!$invoice) {
                return ['success' => false, 'error_code' => 'NOT_FOUND', 'message' => 'Invoice not found', 'http_code' => 404];
            }

            // Load SMTP settings
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM email_settings WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $emailSettings = $stmt->fetch();

            if (!$emailSettings || empty($emailSettings['smtp_host'])) {
                return [
                    'success'    => false,
                    'error_code' => 'SMTP_NOT_CONFIGURED',
                    'message'    => 'SMTP is not configured. Please set up email settings first.',
                    'http_code'  => 422
                ];
            }

            // Build HTML email body
            $htmlBody = $this->buildEmailHtml($invoice, $message);

            // Optional PDF attachment
            $attachments = [];
            if ($attachPdf) {
                $pdfResult = $this->getPdfPath($invoiceId, $userId);
                if ($pdfResult['success']) {
                    $attachments[] = [
                        'path' => $pdfResult['path'],
                        'name' => 'Invoice_' . $invoice['invoice_number'] . '.pdf',
                        'mime' => 'application/pdf',
                    ];
                }
            }

            // Send
            $mailer = SmtpMailer::fromSettings($emailSettings);
            $result = $mailer->send($toEmail, $toName, $subject, $htmlBody, null, $attachments);

            if ($result['success']) {
                // Log the email send
                $this->logEmailSent($db, $userId, $invoiceId, $toEmail, $subject);

                return [
                    'success' => true,
                    'message' => "Invoice emailed to {$toEmail} successfully."
                ];
            } else {
                return [
                    'success'    => false,
                    'error_code' => 'SMTP_SEND_FAILED',
                    'message'    => 'Failed to send email: ' . $result['error'],
                    'http_code'  => 500
                ];
            }
        } catch (Exception $e) {
            return [
                'success'    => false,
                'error_code' => 'EMAIL_SEND_ERROR',
                'message'    => 'Email error: ' . $e->getMessage(),
                'http_code'  => 500
            ];
        }
    }

    /**
     * POST email.settings.test
     * Body: { smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_email, smtp_from_name }
     */
    public function testConnection($input)
    {
        try {
            authenticateRequest();

            $required = ['smtp_host', 'smtp_username', 'smtp_password'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    return ['success' => false, 'error_code' => 'VALIDATION_ERROR', 'message' => "Field '{$field}' is required", 'http_code' => 400];
                }
            }

            $result = SmtpMailer::testConnection($input);

            if ($result['success']) {
                return ['success' => true, 'message' => 'SMTP connection successful!'];
            } else {
                return ['success' => false, 'error_code' => 'SMTP_TEST_FAILED', 'message' => $result['error'], 'http_code' => 422];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error_code' => 'SMTP_TEST_ERROR', 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildEmailHtml(array $invoice, string $customMessage): string
    {
        $invNumber   = htmlspecialchars($invoice['invoice_number']);
        $clientName  = htmlspecialchars($invoice['client_name']);
        $bizName     = htmlspecialchars($invoice['business_name'] ?? $invoice['business_name_snapshot'] ?? '');
        $total       = number_format((float)$invoice['total_amount'], 2);
        $currency    = htmlspecialchars($invoice['currency'] ?? 'INR');
        $dueDate     = htmlspecialchars($invoice['due_date'] ?? '');
        $msgHtml     = nl2br(htmlspecialchars($customMessage));

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr><td style="background:#6366f1;padding:32px 40px;">
          <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;">{$bizName}</h1>
          <p style="margin:6px 0 0;color:#c7d2fe;font-size:14px;">Invoice #{$invNumber}</p>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:36px 40px;">
          <p style="margin:0 0 20px;color:#374151;font-size:15px;">Dear {$clientName},</p>
          <p style="margin:0 0 24px;color:#374151;font-size:15px;line-height:1.6;">{$msgHtml}</p>
          <!-- Invoice Summary Box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:28px;">
            <tr><td style="padding:20px 24px;">
              <table width="100%" cellpadding="4" cellspacing="0">
                <tr>
                  <td style="color:#6b7280;font-size:13px;">Invoice Number</td>
                  <td align="right" style="color:#111827;font-size:13px;font-weight:600;">#{$invNumber}</td>
                </tr>
                <tr>
                  <td style="color:#6b7280;font-size:13px;">Due Date</td>
                  <td align="right" style="color:#111827;font-size:13px;font-weight:600;">{$dueDate}</td>
                </tr>
                <tr>
                  <td style="color:#6b7280;font-size:14px;font-weight:700;padding-top:12px;border-top:1px solid #e2e8f0;">Amount Due</td>
                  <td align="right" style="color:#6366f1;font-size:18px;font-weight:800;padding-top:12px;border-top:1px solid #e2e8f0;">{$currency} {$total}</td>
                </tr>
              </table>
            </td></tr>
          </table>
          <p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.5;">
            Please refer to the attached PDF for the full invoice details. If you have any questions, feel free to reply to this email.
          </p>
        </td></tr>
        <!-- Footer -->
        <tr><td style="background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
            This email was sent by {$bizName} via InvoicePro
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private function getPdfPath(int $invoiceId, int $userId): array
    {
        // Try to find a generated PDF in the storage directory
        $storageDir = __DIR__ . '/../../storage/invoices/';
        $pdfPath    = $storageDir . "invoice_{$invoiceId}.pdf";

        if (file_exists($pdfPath)) {
            return ['success' => true, 'path' => $pdfPath];
        }

        // PDF not cached — generate on the fly using PDFService if available
        if (file_exists(__DIR__ . '/../services/PDFService.php')) {
            require_once __DIR__ . '/../services/PDFService.php';
            require_once __DIR__ . '/../vendor/autoload.php';
            $pdfService = new PDFService();
            $result     = $pdfService->generateInvoicePDF($invoiceId, $userId);
            if ($result['success'] && !empty($result['file_path']) && file_exists($result['file_path'])) {
                return ['success' => true, 'path' => $result['file_path']];
            }
        }

        return ['success' => false, 'error' => 'PDF not available'];
    }

    private function logEmailSent($db, int $userId, int $invoiceId, string $toEmail, string $subject): void
    {
        try {
            // Check if email_logs table exists before inserting
            $stmt = $db->query("SHOW TABLES LIKE 'email_logs'");
            if ($stmt->rowCount() > 0) {
                $ins = $db->prepare("
                    INSERT INTO email_logs (user_id, invoice_id, to_email, subject, sent_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $ins->execute([$userId, $invoiceId, $toEmail, $subject]);
            }
        } catch (Exception $e) {
            // Non-fatal — just log
            error_log('Email log insert failed: ' . $e->getMessage());
        }
    }
}
