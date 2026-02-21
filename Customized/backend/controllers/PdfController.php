<?php
// PDF Controller - Secure PDF generation and delivery

require_once __DIR__ . '/../services/PDFService.php';

class PdfController
{

    public function generatePdf($input)
    {
        try {
            $userId = authenticateRequest();
            $invoiceId = $_GET['id'] ?? null;
            $template = $_GET['template'] ?? 'default';

            if (!$invoiceId) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invoice ID is required',
                    'http_code' => 400
                ];
            }

            $pdfService = new PDFService();
            $result = $pdfService->generateInvoicePDF($invoiceId, $userId, $template);

            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => [
                        'pdf_path' => $result['pdf_path']
                    ],
                    'message' => 'PDF generated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error_code' => 'PDF_GENERATION_FAILED',
                    'message' => $result['error'],
                    'http_code' => 500
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'PDF_GENERATION_ERROR',
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }

    public function downloadPdf($input)
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

            $pdfService = new PDFService();
            $result = $pdfService->getPdfForDownload($invoiceId, $userId);

            if ($result['success']) {
                // Set headers for PDF download
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="invoice_' . $invoiceId . '.pdf"');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');

                // Output the PDF file
                readfile($result['file_path']);
                exit(); // Important: exit after sending the file

            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error_code' => 'PDF_DOWNLOAD_FAILED',
                    'message' => $result['error']
                ]);
                exit();
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error_code' => 'PDF_DOWNLOAD_ERROR',
                'message' => 'Failed to download PDF: ' . $e->getMessage()
            ]);
            exit();
        }
    }
}
