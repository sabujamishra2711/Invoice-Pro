<?php
// Invoice Management Controller (Extended)

require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../services/InvoiceService.php';

class InvoiceUpdateController
{

    public function update($input)
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

            // Validate input
            if (isset($input['items'])) {
                $validation = Validator::validateInvoiceData(['items' => $input['items']]);
                if ($validation !== true) {
                    return [
                        'success' => false,
                        'error_code' => 'VALIDATION_ERROR',
                        'message' => 'Validation failed',
                        'data' => ['errors' => $validation],
                        'http_code' => 400
                    ];
                }
            }

            // Use InvoiceService for update with validation
            $invoiceService = new InvoiceService();
            $invoice = $invoiceService->updateInvoice($invoiceId, $userId, $input);

            return [
                'success' => true,
                'data' => ['invoice' => $invoice],
                'message' => 'Invoice updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'INVOICE_UPDATE_FAILED',
                'message' => 'Failed to update invoice: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }

    public function delete($input)
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

            // Use InvoiceService for delete with validation
            $invoiceService = new InvoiceService();
            $result = $invoiceService->deleteInvoice($invoiceId, $userId);

            return [
                'success' => true,
                'data' => [],
                'message' => 'Invoice deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'INVOICE_DELETE_FAILED',
                'message' => 'Failed to delete invoice: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }
}
