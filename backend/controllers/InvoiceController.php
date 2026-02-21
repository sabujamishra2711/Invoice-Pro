<?php
// Invoice Management Controller

require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../services/InvoiceService.php';

class InvoiceController
{

    public function list($input)
    {
        try {
            $userId = authenticateRequest();

            $db = getDB();

            // Get status filter from query params if present
            $statusFilter = $_GET['status'] ?? null;

            // Build base query
            $sql = "
                SELECT 
                    i.*,
                    c.name as client_name,
                    c.company as client_company,
                    CASE 
                        WHEN i.paid_amount >= i.total_amount THEN 'paid'
                        WHEN i.paid_amount > 0 THEN 'partial'
                        WHEN i.paid_amount < i.total_amount AND i.due_date < CURDATE() AND i.deleted_at IS NULL THEN 'overdue'
                        ELSE 'sent'
                    END as calculated_status,
                    CASE 
                        WHEN i.paid_amount > 0 THEN 1 
                        ELSE 0 
                    END as is_locked
                FROM invoices i
                JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ? AND i.deleted_at IS NULL
            ";

            $params = [$userId];

            // Add status filter using conditional logic instead of stored status
            if ($statusFilter) {
                switch ($statusFilter) {
                    case 'paid':
                        $sql .= " AND i.paid_amount >= i.total_amount";
                        break;
                    case 'partial':
                        $sql .= " AND i.paid_amount > 0 AND i.paid_amount < i.total_amount";
                        break;
                    case 'overdue':
                        $sql .= " AND i.paid_amount < i.total_amount AND i.due_date < CURDATE()";
                        break;
                    case 'sent':
                        $sql .= " AND i.paid_amount = 0 AND i.due_date >= CURDATE()";
                        break;
                    default:
                        // Invalid status filter, ignore
                        break;
                }
            }

            $sql .= " ORDER BY i.created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll();

            return [
                'success' => true,
                'data' => ['invoices' => $invoices]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'INVOICE_LIST_FAILED',
                'message' => 'Failed to fetch invoices',
                'http_code' => 500
            ];
        }
    }

    public function create($input)
    {
        try {
            $userId = authenticateRequest();

            // Validate input
            $validation = Validator::validateInvoiceData($input);
            if ($validation !== true) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validation],
                    'http_code' => 400
                ];
            }

            // Check if client belongs to user
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM clients WHERE id = ? AND user_id = ?");
            $stmt->execute([$input['client_id'], $userId]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'error_code' => 'CLIENT_ACCESS_DENIED',
                    'message' => 'Client not found or access denied',
                    'http_code' => 403
                ];
            }

            // Use InvoiceService for atomic creation
            $invoiceService = new InvoiceService();
            $invoice = $invoiceService->createInvoice($userId, $input['client_id'], $input);

            return [
                'success' => true,
                'data' => ['invoice' => $invoice],
                'message' => 'Invoice created successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'INVOICE_CREATE_FAILED',
                'message' => 'Failed to create invoice: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }

    public function get($input)
    {
        try {
            $userId = authenticateRequest();
            $invoiceId = $_GET['id'] ?? null;

            if (!$invoiceId) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invoice ID is required',
                    'http_code' => 400
                ];
            }

            // Use InvoiceService for dynamic status calculation
            $invoiceService = new InvoiceService();
            $invoice = $invoiceService->getInvoiceWithDetails($invoiceId, $userId);

            if (!$invoice) {
                return [
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Invoice not found',
                    'http_code' => 404
                ];
            }

            // Get invoice items
            $db = getDB();
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

            // Add locked status for UI
            $invoice['is_locked'] = $invoiceService->isInvoiceLocked($invoice) ? 1 : 0;
            $invoice['calculated_status'] = $invoiceService->getDisplayStatus($invoice);

            return [
                'success' => true,
                'data' => [
                    'invoice' => $invoice,
                    'items' => $items,
                    'payments' => $payments
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'INVOICE_GET_FAILED',
                'message' => 'Failed to fetch invoice details',
                'http_code' => 500
            ];
        }
    }

    public function duplicate($input)
    {
        try {
            $userId = authenticateRequest();
            $invoiceId = $_GET['id'] ?? null;

            if (!$invoiceId) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invoice ID is required',
                    'http_code' => 400
                ];
            }

            $invoiceService = new InvoiceService();
            $newInvoice = $invoiceService->duplicateInvoice($invoiceId, $userId);

            return [
                'success' => true,
                'data' => ['invoice' => $newInvoice],
                'message' => 'Invoice duplicated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'INVOICE_DUPLICATE_FAILED',
                'message' => 'Failed to duplicate invoice: ' . $e->getMessage(),
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
                    i.total_amount,
                    i.paid_amount,
                    (i.total_amount - i.paid_amount) as balance,
                    i.issue_date,
                    i.due_date,
                    CASE 
                        WHEN i.paid_amount >= i.total_amount THEN 'paid'
                        WHEN i.paid_amount > 0 THEN 'partial'
                        WHEN i.due_date < CURDATE() THEN 'overdue'
                        ELSE 'sent'
                    END as status,
                    i.currency
                FROM invoices i
                JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ? AND i.deleted_at IS NULL
                ORDER BY i.created_at DESC
            ");
            $stmt->execute([$userId]);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $date = date('Y-m-d');
            $filename = "invoices_export_{$date}.csv";

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
            fputcsv($output, ['Invoice #', 'Client', 'Amount', 'Paid', 'Balance', 'Issue Date', 'Due Date', 'Status', 'Currency']);

            // Data rows
            foreach ($invoices as $inv) {
                fputcsv($output, [
                    $inv['invoice_number'],
                    $inv['client_name'],
                    $inv['total_amount'],
                    $inv['paid_amount'],
                    $inv['balance'],
                    $inv['issue_date'],
                    $inv['due_date'],
                    $inv['status'],
                    $inv['currency']
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
