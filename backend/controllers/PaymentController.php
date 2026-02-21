<?php
// Payment Management Controller

require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../services/PaymentService.php';

class PaymentController
{

    public function create($input)
    {
        try {
            $userId = authenticateRequest();

            // Extract idempotency key from headers
            $headers = getallheaders();
            $idempotencyKey = isset($headers['Idempotency-Key']) ? $headers['Idempotency-Key'] : null;

            // Validate input
            $validation = Validator::validatePaymentData($input);
            if ($validation !== true) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validation],
                    'http_code' => 400
                ];
            }

            // Check if invoice belongs to user
            $db = getDB();
            $stmt = $db->prepare("
                SELECT i.id 
                FROM invoices i 
                WHERE i.id = ? AND i.user_id = ? AND i.deleted_at IS NULL
            ");
            $stmt->execute([$input['invoice_id'], $userId]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'error_code' => 'INVOICE_ACCESS_DENIED',
                    'message' => 'Invoice not found or access denied',
                    'http_code' => 403
                ];
            }

            // Use PaymentService for transactional payment recording
            $paymentService = new PaymentService();
            $result = $paymentService->addPayment(
                $userId,
                $input['invoice_id'],
                $input['amount'],
                $input['payment_date'],
                $input['method'],
                $input['reference'] ?? null,
                $idempotencyKey
            );

            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_CREATE_FAILED',
                'message' => 'Failed to record payment: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }

    public function list($input)
    {
        try {
            $userId = authenticateRequest();

            $db = getDB();

            // Get all payments for user
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    i.invoice_number,
                    c.name as client_name
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ?
                ORDER BY p.payment_date DESC, p.created_at DESC
            ");
            $stmt->execute([$userId]);
            $payments = $stmt->fetchAll();

            return [
                'success' => true,
                'data' => ['payments' => $payments]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'PAYMENT_LIST_FAILED',
                'message' => 'Failed to fetch payments',
                'http_code' => 500
            ];
        }
    }

    public function exportCsv($input)
    {
        try {
            $userId = authenticateRequest();
            if (!$userId) {
                http_response_code(401);
                echo 'Unauthorized';
                exit();
            }

            $db = getDB();
            $stmt = $db->prepare("
                SELECT 
                    i.invoice_number,
                    c.name as client_name,
                    p.amount,
                    p.payment_date,
                    p.method,
                    p.reference
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ?
                ORDER BY p.payment_date DESC, p.created_at DESC
            ");
            $stmt->execute([$userId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $date = date('Y-m-d');
            $filename = "payments_export_{$date}.csv";

            // Clear any previous output
            if (ob_get_level()) ob_end_clean();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($output, ['Invoice #', 'Client', 'Amount', 'Date', 'Method', 'Reference']);

            // Data rows
            foreach ($payments as $p) {
                fputcsv($output, [
                    $p['invoice_number'],
                    $p['client_name'],
                    $p['amount'],
                    $p['payment_date'],
                    $p['method'],
                    $p['reference']
                ]);
            }

            fclose($output);
            exit();
        } catch (Exception $e) {
            http_response_code(500);
            echo 'Export failed: ' . $e->getMessage();
            exit();
        }
    }
}
