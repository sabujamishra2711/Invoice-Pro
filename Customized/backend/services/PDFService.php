<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/ObjectStorageService.php';

class PDFService
{
    private $db;
    private $objectStorage;

    public function __construct()
    {
        $this->db = getDB();
        $this->objectStorage = new ObjectStorageService();
    }

    public function generateInvoicePDF($invoiceId, $userId, $template = 'default')
    {
        try {
            // Fetch invoice data with snapshots for historical integrity
            $stmt = $this->db->prepare("
                SELECT 
                    i.*,
                    -- Snapshot client data to preserve historical integrity
                    i.client_name_snapshot,
                    i.client_company_snapshot,
                    i.client_email_snapshot,
                    i.client_phone_snapshot,
                    i.client_address_snapshot,
                    i.client_gst_snapshot,
                    -- Snapshot business data to preserve historical integrity
                    i.business_name_snapshot,
                    i.business_address_snapshot,
                    i.business_gst_snapshot,
                    i.business_logo_path_snapshot
                FROM invoices i
                WHERE i.id = ? AND i.user_id = ? AND i.deleted_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$invoiceId, $userId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                return ['success' => false, 'message' => 'Invoice not found or access denied'];
            }

            // Fetch invoice items
            $stmt = $this->db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
            $stmt->execute([$invoiceId]);
            $items = $stmt->fetchAll();

            // Generate HTML content for the invoice based on template
            $html = $this->generateHTML($invoice, $items, $template);

            // Create TCPDF instance
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator('Invoice Management System');
            $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);

            // Set default header and footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);

            // Add a page
            $pdf->AddPage();

            // Output the HTML content
            $pdf->writeHTML($html, true, false, true, false, '');

            // Define the object key for storage
            $objectKey = "invoices/{$invoiceId}/" . $invoice['invoice_number'] . '.pdf';

            // Get PDF as string
            $pdfContent = $pdf->Output('', 'S');

            // Upload to object storage instead of saving locally
            $uploadSuccess = $this->objectStorage->upload($objectKey, $pdfContent, 'application/pdf');

            if (!$uploadSuccess) {
                return ['success' => false, 'message' => 'Failed to save PDF to storage'];
            }

            // Update invoice record to mark PDF as not dirty and store object key
            $updateStmt = $this->db->prepare("UPDATE invoices SET pdf_dirty = 0, pdf_path = ? WHERE id = ?");
            $updateStmt->execute([$objectKey, $invoiceId]);

            // Get signed URL for temporary access
            $signedUrl = $this->objectStorage->getSignedUrl($objectKey);

            return [
                'success' => true,
                'message' => 'PDF generated and saved successfully',
                'pdf_url' => $signedUrl,
                'pdf_path' => $objectKey
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error generating PDF: ' . $e->getMessage()];
        }
    }

    public function regenerateInvoicePDF($invoiceId, $userId, $template = 'default')
    {
        // First, verify that the user owns this invoice
        $stmt = $this->db->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$invoiceId, $userId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found or access denied'];
        }

        // Mark the invoice as needing PDF regeneration
        $stmt = $this->db->prepare("UPDATE invoices SET pdf_dirty = 1 WHERE id = ?");
        $stmt->execute([$invoiceId]);

        // Generate the PDF with specified template
        return $this->generateInvoicePDF($invoiceId, $userId, $template);
    }

    private function generateHTML($invoice, $items, $template = 'default')
    {
        switch($template) {
            case 'minimal':
                return $this->generateMinimalTemplate($invoice, $items);
            case 'modern':
                return $this->generateModernTemplate($invoice, $items);
            case 'classic':
                return $this->generateClassicTemplate($invoice, $items);
            case 'professional':
                return $this->generateProfessionalTemplate($invoice, $items);
            case 'elegant':
                return $this->generateElegantTemplate($invoice, $items);
            case 'creative':
                return $this->generateCreativeTemplate($invoice, $items);
            case 'corporate':
                return $this->generateCorporateTemplate($invoice, $items);
            case 'simple':
                return $this->generateSimpleTemplate($invoice, $items);
            case 'colorful':
                return $this->generateColorfulTemplate($invoice, $items);
            case 'luxury':
                return $this->generateLuxuryTemplate($invoice, $items);
            default:
                return $this->generateDefaultTemplate($invoice, $items);
        }
    }

    private function generateDefaultTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 15px; }
                .company-info { text-align: left; }
                .invoice-title { text-align: center; font-size: 24px; font-weight: bold; color: #333; }
                .invoice-details { text-align: right; }
                .client-info { margin: 20px 0; }
                .invoice-meta { margin: 20px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .totals { margin-left: auto; width: 300px; }
                .footer { margin-top: 40px; border-top: 2px solid #333; padding-top: 15px; }
            </style>
        </head>
        <body>';

        $html .= '<div class="header">';

        // Company info section
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
              $logoFile = LOGO_STORAGE_PATH . basename($invoice['business_logo_path_snapshot']);
              if (file_exists($logoFile)) {
                  $logoData   = base64_encode(file_get_contents($logoFile));
                  $logoMime   = mime_content_type($logoFile) ?: 'image/png';
                  $logoSrc    = 'data:' . $logoMime . ';base64,' . $logoData;
              } else {
                  $logoSrc = LOGO_PUBLIC_URL . basename($invoice['business_logo_path_snapshot']);
              }
              $html .= '<img src="' . $logoSrc . '" alt="Logo" style="max-width: 150px; margin-bottom: 10px;"><br>';
          }
        $html .= '<strong>' . htmlspecialchars($invoice['business_name_snapshot']) . '</strong><br>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '<br>';
        }
        if (!empty($invoice['business_gst_snapshot'])) {
            $html .= 'GSTIN: ' . htmlspecialchars($invoice['business_gst_snapshot']) . '<br>';
        }
        $html .= '</div>';

        // Invoice title and details
        $html .= '<div class="invoice-title">INVOICE</div>';

        $html .= '<div class="invoice-details">';
        $html .= '<strong>Invoice #: </strong>' . htmlspecialchars($invoice['invoice_number']) . '<br>';
        $html .= '<strong>Date: </strong>' . date('M d, Y', strtotime($invoice['issue_date'])) . '<br>';
        $html .= '<strong>Due Date: </strong>' . date('M d, Y', strtotime($invoice['due_date'])) . '<br>';
        $html .= '</div>';

        $html .= '</div>';

        // Client info
        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong><br>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= htmlspecialchars($invoice['client_company_snapshot']) . '<br>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '<br>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= 'Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '<br>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= 'Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '<br>';
        }
        if (!empty($invoice['client_gst_snapshot'])) {
            $html .= 'GSTIN: ' . htmlspecialchars($invoice['client_gst_snapshot']) . '<br>';
        }
        $html .= '</div>';

        // Invoice meta
        $html .= '<div class="invoice-meta">';
        $html .= '<strong>Status:</strong> ' . ucfirst($this->getDisplayStatus($invoice)) . '<br>';
        $html .= '<strong>Currency:</strong> ' . htmlspecialchars($invoice['currency']) . '<br>';
        $html .= '</div>';

        // Items table
        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        // Totals
        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><td><strong>Subtotal:</strong></td><td align="right">' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><td><strong>Tax Amount:</strong></td><td align="right">' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><td><strong>Total Amount:</strong></td><td align="right">' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><td><strong>Paid Amount:</strong></td><td align="right">' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><td><strong>Balance Due:</strong></td><td align="right">' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Notes
        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Notes:</h4>';
            $html .= nl2br(htmlspecialchars($invoice['notes']));
            $html .= '</div>';
        }

        // Footer
        $html .= '<div class="footer">';
        $html .= '<em>Thank you for your business!</em>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    private function generateMinimalTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { 
                    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; 
                    margin: 0; 
                    padding: 30px; 
                    color: #333;
                    background-color: #fff;
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 30px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #eee;
                }
                .company-info h2 { 
                    margin: 0; 
                    font-weight: 300; 
                    letter-spacing: 1px;
                    color: #222;
                }
                .company-info p { 
                    margin: 5px 0 0 0; 
                    color: #777; 
                    font-size: 14px;
                }
                .invoice-header { 
                    text-align: right; 
                }
                .invoice-header h1 { 
                    margin: 0; 
                    font-size: 36px; 
                    font-weight: 300; 
                    color: #222;
                }
                .invoice-details { 
                    margin: 0; 
                    font-size: 14px; 
                    line-height: 1.6;
                }
                .client-info { 
                    margin: 20px 0; 
                    padding: 15px;
                    background-color: #f9f9f9;
                    border-radius: 4px;
                }
                .client-info h3 { 
                    margin: 0 0 10px 0; 
                    font-weight: 500;
                    color: #222;
                }
                .client-info p { 
                    margin: 5px 0; 
                    font-size: 14px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 25px 0;
                    font-size: 14px;
                }
                th { 
                    background-color: #f8f8f8; 
                    padding: 12px 15px;
                    text-align: left;
                    font-weight: 500;
                    color: #555;
                    border-bottom: 2px solid #eee;
                }
                td { 
                    padding: 12px 15px; 
                    border-bottom: 1px solid #eee;
                }
                tr:last-child td { border-bottom: none; }
                .totals { 
                    margin-left: auto; 
                    width: 280px; 
                    margin-top: 20px;
                }
                .totals table { 
                    border: none;
                }
                .totals th, .totals td { 
                    border: none; 
                    padding: 8px 0;
                    text-align: right;
                }
                .totals tr:nth-child(even) { background-color: transparent; }
                .footer { 
                    margin-top: 40px; 
                    padding-top: 20px; 
                    border-top: 1px solid #eee;
                    text-align: center;
                    color: #777;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>';

        $html .= '<div class="header">';
        
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
            $logoPath = $invoice['business_logo_path_snapshot'];
            $html .= '<img src="' . $logoPath . '" alt="Logo" style="max-height: 50px; margin-bottom: 10px;"><br>';
        }
        $html .= '<h2>' . htmlspecialchars($invoice['business_name_snapshot']) . '</h2>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="invoice-header">';
        $html .= '<h1>INVOICE</h1>';
        $html .= '<div class="invoice-details">';
        $html .= '<p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('M d, Y', strtotime($invoice['issue_date'])) . '</p>';
        $html .= '<p><strong>Due Date:</strong> ' . date('M d, Y', strtotime($invoice['due_date'])) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p><strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong></p>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= '<p>' . htmlspecialchars($invoice['client_company_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '</p>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= '<p>Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= '<p>Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><th>Subtotal:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><th>Tax Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Total Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Paid Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Balance Due:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Notes:</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '<div class="footer">';
        $html .= '<p>Thank you for your business!</p>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    private function generateModernTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { 
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 40px; 
                    color: #333;
                    background-color: #f8f9fa;
                }
                .container { 
                    max-width: 800px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 40px; 
                    box-shadow: 0 0 20px rgba(0,0,0,0.05);
                    border-radius: 8px;
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 40px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #007bff;
                }
                .company-info h2 { 
                    margin: 0; 
                    font-weight: 700; 
                    color: #007bff;
                    font-size: 24px;
                }
                .company-info p { 
                    margin: 8px 0 0 0; 
                    color: #6c757d; 
                    font-size: 14px;
                }
                .invoice-header { 
                    text-align: right; 
                }
                .invoice-header h1 { 
                    margin: 0; 
                    font-size: 36px; 
                    font-weight: 700; 
                    color: #212529;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .invoice-details { 
                    margin: 15px 0 0 0; 
                    font-size: 15px; 
                    line-height: 1.6;
                }
                .client-info { 
                    margin: 30px 0; 
                    padding: 20px;
                    background-color: #f8f9fa;
                    border-radius: 6px;
                    border-left: 4px solid #007bff;
                }
                .client-info h3 { 
                    margin: 0 0 15px 0; 
                    font-weight: 600;
                    color: #212529;
                    font-size: 18px;
                }
                .client-info p { 
                    margin: 8px 0; 
                    font-size: 14px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 30px 0;
                    font-size: 14px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.05);
                }
                th { 
                    background-color: #007bff; 
                    color: white;
                    padding: 15px;
                    text-align: left;
                    font-weight: 600;
                }
                td { 
                    padding: 15px; 
                    border-bottom: 1px solid #dee2e6;
                }
                tr:nth-child(even) { 
                    background-color: #f8f9fa; 
                }
                tr:last-child td { border-bottom: none; }
                .totals { 
                    margin-left: auto; 
                    width: 300px; 
                    margin-top: 30px;
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 6px;
                }
                .totals table { 
                    border: none;
                    width: 100%;
                }
                .totals th, .totals td { 
                    border: none; 
                    padding: 10px 0;
                    text-align: right;
                }
                .footer { 
                    margin-top: 50px; 
                    padding-top: 25px; 
                    border-top: 1px solid #dee2e6;
                    text-align: center;
                    color: #6c757d;
                    font-size: 14px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 5px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    margin-top: 10px;
                }
                .status-paid { background-color: #d4edda; color: #155724; }
                .status-partial { background-color: #fff3cd; color: #856404; }
                .status-overdue { background-color: #f8d7da; color: #721c24; }
                .status-sent { background-color: #cce5ff; color: #004085; }
            </style>
        </head>
        <body>
        <div class="container">';

        $html .= '<div class="header">';
        
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
            $logoPath = $invoice['business_logo_path_snapshot'];
            $html .= '<img src="' . $logoPath . '" alt="Logo" style="max-height: 60px; margin-bottom: 15px;"><br>';
        }
        $html .= '<h2>' . htmlspecialchars($invoice['business_name_snapshot']) . '</h2>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="invoice-header">';
        $html .= '<h1>INVOICE</h1>';
        $html .= '<div class="invoice-details">';
        $html .= '<p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('M d, Y', strtotime($invoice['issue_date'])) . '</p>';
        $html .= '<p><strong>Due Date:</strong> ' . date('M d, Y', strtotime($invoice['due_date'])) . '</p>';
        $html .= '<span class="status-badge status-' . $this->getDisplayStatus($invoice) . '">' . ucfirst($this->getDisplayStatus($invoice)) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p><strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong></p>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= '<p>' . htmlspecialchars($invoice['client_company_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '</p>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= '<p>Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= '<p>Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><th>Subtotal:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><th>Tax Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Total Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Paid Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Balance Due:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Notes:</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '<div class="footer">';
        $html .= '<p>Thank you for your business!</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    private function generateClassicTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { 
                    font-family: Georgia, "Times New Roman", Times, serif; 
                    margin: 0; 
                    padding: 40px; 
                    color: #2c3e50;
                    background-color: #f9f9f9;
                }
                .container { 
                    max-width: 800px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 50px; 
                    border: 1px solid #e0e0e0;
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 40px;
                    padding-bottom: 25px;
                    border-bottom: 3px double #bdc3c7;
                }
                .company-info h2 { 
                    margin: 0; 
                    font-weight: bold; 
                    color: #2c3e50;
                    font-size: 28px;
                    letter-spacing: 1px;
                }
                .company-info p { 
                    margin: 10px 0 0 0; 
                    color: #7f8c8d; 
                    font-size: 14px;
                    line-height: 1.6;
                }
                .invoice-header { 
                    text-align: right; 
                }
                .invoice-header h1 { 
                    margin: 0; 
                    font-size: 42px; 
                    font-weight: bold; 
                    color: #2c3e50;
                    letter-spacing: 3px;
                    text-transform: uppercase;
                }
                .invoice-details { 
                    margin: 20px 0 0 0; 
                    font-size: 15px; 
                    line-height: 1.8;
                }
                .client-info { 
                    margin: 35px 0; 
                    padding: 25px;
                    border: 1px solid #e0e0e0;
                    border-radius: 4px;
                }
                .client-info h3 { 
                    margin: 0 0 15px 0; 
                    font-weight: bold;
                    color: #2c3e50;
                    font-size: 18px;
                    border-bottom: 1px solid #ecf0f1;
                    padding-bottom: 8px;
                }
                .client-info p { 
                    margin: 10px 0; 
                    font-size: 14px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 35px 0;
                    font-size: 14px;
                }
                th { 
                    background-color: #ecf0f1; 
                    padding: 15px;
                    text-align: left;
                    font-weight: bold;
                    color: #2c3e50;
                    border: 1px solid #bdc3c7;
                }
                td { 
                    padding: 15px; 
                    border: 1px solid #bdc3c7;
                }
                tr:nth-child(even) { 
                    background-color: #f8f9fa; 
                }
                .totals { 
                    margin-left: auto; 
                    width: 300px; 
                    margin-top: 35px;
                    border: 1px solid #bdc3c7;
                    padding: 20px;
                }
                .totals table { 
                    border: none;
                    width: 100%;
                }
                .totals th, .totals td { 
                    border: none; 
                    padding: 8px 0;
                    text-align: right;
                }
                .footer { 
                    margin-top: 50px; 
                    padding-top: 25px; 
                    border-top: 3px double #bdc3c7;
                    text-align: center;
                    color: #7f8c8d;
                    font-size: 14px;
                    font-style: italic;
                }
            </style>
        </head>
        <body>
        <div class="container">';

        $html .= '<div class="header">';
        
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
            $logoPath = $invoice['business_logo_path_snapshot'];
            $html .= '<img src="' . $logoPath . '" alt="Logo" style="max-height: 70px; margin-bottom: 15px;"><br>';
        }
        $html .= '<h2>' . htmlspecialchars($invoice['business_name_snapshot']) . '</h2>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="invoice-header">';
        $html .= '<h1>INVOICE</h1>';
        $html .= '<div class="invoice-details">';
        $html .= '<p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('F d, Y', strtotime($invoice['issue_date'])) . '</p>';
        $html .= '<p><strong>Due Date:</strong> ' . date('F d, Y', strtotime($invoice['due_date'])) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p><strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong></p>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= '<p>' . htmlspecialchars($invoice['client_company_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '</p>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= '<p>Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= '<p>Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Quantity</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><th>Subtotal:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><th>Tax Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Total Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Paid Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Balance Due:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Additional Notes:</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '<div class="footer">';
        $html .= '<p>We appreciate your business. Thank you for your patronage.</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    private function generateProfessionalTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { 
                    font-family: "Arial", sans-serif; 
                    margin: 0; 
                    padding: 30px; 
                    color: #2c3e50;
                    background-color: #ffffff;
                }
                .container { 
                    max-width: 800px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 30px; 
                    border: 1px solid #dfe6e9;
                    box-shadow: 0 0 15px rgba(0,0,0,0.05);
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #2c3e50;
                }
                .company-info h2 { 
                    margin: 0; 
                    font-weight: bold; 
                    color: #2c3e50;
                    font-size: 22px;
                    letter-spacing: 0.5px;
                }
                .company-info p { 
                    margin: 8px 0 0 0; 
                    color: #7f8c8d; 
                    font-size: 13px;
                    line-height: 1.5;
                }
                .invoice-header { 
                    text-align: right; 
                }
                .invoice-header h1 { 
                    margin: 0; 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #2c3e50;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .invoice-details { 
                    margin: 15px 0 0 0; 
                    font-size: 14px; 
                    line-height: 1.6;
                }
                .invoice-meta { 
                    display: flex; 
                    justify-content: space-between; 
                    margin: 20px 0; 
                    padding: 15px; 
                    background-color: #f8f9fa; 
                    border-radius: 4px;
                }
                .client-info { 
                    margin: 25px 0; 
                    padding: 20px;
                    background-color: #f8f9fa;
                    border-left: 3px solid #2c3e50;
                    border-radius: 0 4px 4px 0;
                }
                .client-info h3 { 
                    margin: 0 0 15px 0; 
                    font-weight: bold;
                    color: #2c3e50;
                    font-size: 16px;
                }
                .client-info p { 
                    margin: 8px 0; 
                    font-size: 14px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 30px 0;
                    font-size: 14px;
                    border: 1px solid #dfe6e9;
                }
                th { 
                    background-color: #2c3e50; 
                    color: white;
                    padding: 12px 15px;
                    text-align: left;
                    font-weight: bold;
                }
                td { 
                    padding: 12px 15px; 
                    border-bottom: 1px solid #dfe6e9;
                }
                tr:last-child td { border-bottom: none; }
                .totals { 
                    margin-left: auto; 
                    width: 280px; 
                    margin-top: 30px;
                    border: 1px solid #dfe6e9;
                    border-radius: 4px;
                    overflow: hidden;
                }
                .totals table { 
                    border: none;
                    width: 100%;
                }
                .totals th, .totals td { 
                    border: none; 
                    padding: 10px 15px;
                    text-align: right;
                }
                .footer { 
                    margin-top: 40px; 
                    padding-top: 20px; 
                    border-top: 1px solid #dfe6e9;
                    text-align: center;
                    color: #7f8c8d;
                    font-size: 13px;
                }
            </style>
        </head>
        <body>
        <div class="container">';

        $html .= '<div class="header">';
        
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
            $logoPath = $invoice['business_logo_path_snapshot'];
            $html .= '<img src="' . $logoPath . '" alt="Logo" style="max-height: 55px; margin-bottom: 10px;"><br>';
        }
        $html .= '<h2>' . htmlspecialchars($invoice['business_name_snapshot']) . '</h2>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="invoice-header">';
        $html .= '<h1>INVOICE</h1>';
        $html .= '<div class="invoice-details">';
        $html .= '<p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('M d, Y', strtotime($invoice['issue_date'])) . '</p>';
        $html .= '<p><strong>Due Date:</strong> ' . date('M d, Y', strtotime($invoice['due_date'])) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="invoice-meta">';
        $html .= '<div><strong>Status:</strong> ' . ucfirst($this->getDisplayStatus($invoice)) . '</div>';
        $html .= '<div><strong>Currency:</strong> ' . htmlspecialchars($invoice['currency']) . '</div>';
        $html .= '</div>';

        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p><strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong></p>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= '<p>' . htmlspecialchars($invoice['client_company_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '</p>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= '<p>Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= '<p>Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><th>Subtotal:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><th>Tax Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Total Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Paid Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Balance Due:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Notes:</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '<div class="footer">';
        $html .= '<p>Thank you for your business! Payment is due within 30 days.</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    private function generateCreativeTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");
                body { 
                    font-family: "Poppins", sans-serif; 
                    margin: 0; 
                    padding: 30px; 
                    color: #2d3748;
                    background: linear-gradient(135deg, #f0f4f8, #e2e8f0);
                }
                .container { 
                    max-width: 850px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 40px; 
                    border-radius: 12px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
                    position: relative;
                    overflow: hidden;
                }
                .container::before {
                    content: "";
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 8px;
                    background: linear-gradient(90deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4, #ffeaa7);
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 40px;
                    padding-bottom: 20px;
                    border-bottom: 2px dashed #e2e8f0;
                }
                .company-info h2 { 
                    margin: 0; 
                    font-weight: 700; 
                    color: #2d3748;
                    font-size: 24px;
                    letter-spacing: 0.5px;
                }
                .company-info p { 
                    margin: 8px 0 0 0; 
                    color: #718096; 
                    font-size: 14px;
                    line-height: 1.6;
                }
                .invoice-header { 
                    text-align: right; 
                }
                .invoice-header h1 { 
                    margin: 0; 
                    font-size: 36px; 
                    font-weight: 700; 
                    color: #ff6b6b;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                }
                .invoice-details { 
                    margin: 15px 0 0 0; 
                    font-size: 14px; 
                    line-height: 1.7;
                }
                .client-info { 
                    margin: 30px 0; 
                    padding: 25px;
                    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
                    border-radius: 10px;
                    border-left: 4px solid #4ecdc4;
                    position: relative;
                    overflow: hidden;
                }
                .client-info::before {
                    content: "";
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-image: radial-gradient(circle, #e2e8f0 10%, transparent 11%), radial-gradient(circle at bottom left, #e2e8f0 10%, transparent 11%), radial-gradient(circle at bottom right, #e2e8f0 10%, transparent 11%), radial-gradient(circle at top left, #e2e8f0 10%, transparent 11%), radial-gradient(circle at top right, #e2e8f0 10%, transparent 11%);
                    background-position: 0 0, 0 10px, 10px -10px, -10px 0px, 10px 10px;
                    background-size: 20px 20px;
                    opacity: 0.1;
                }
                .client-info h3 { 
                    margin: 0 0 15px 0; 
                    font-weight: 600;
                    color: #2d3748;
                    font-size: 18px;
                    position: relative;
                    z-index: 1;
                }
                .client-info p { 
                    margin: 10px 0; 
                    font-size: 14px;
                    position: relative;
                    z-index: 1;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 30px 0;
                    font-size: 14px;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
                }
                th { 
                    background: linear-gradient(to bottom, #4ecdc4, #44a08d); 
                    color: white;
                    padding: 16px;
                    text-align: left;
                    font-weight: 500;
                }
                td { 
                    padding: 16px; 
                    border-bottom: 1px solid #e2e8f0;
                }
                tr:nth-child(even) { 
                    background-color: #f8fafc; 
                }
                tr:last-child td { border-bottom: none; }
                .totals { 
                    margin-left: auto; 
                    width: 280px; 
                    margin-top: 30px;
                    background: linear-gradient(135deg, #4ecdc4, #44a08d);
                    border-radius: 10px;
                    padding: 25px;
                    color: white;
                }
                .totals table { 
                    border: none;
                    width: 100%;
                }
                .totals th, .totals td { 
                    border: none; 
                    padding: 10px 0;
                    text-align: right;
                    color: white;
                }
                .footer { 
                    margin-top: 50px; 
                    padding-top: 25px; 
                    border-top: 2px dashed #e2e8f0;
                    text-align: center;
                    color: #718096;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
        <div class="container">';

        $html .= '<div class="header">';
        
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
            $logoFile = LOGO_STORAGE_PATH . basename($invoice['business_logo_path_snapshot']);
            if (file_exists($logoFile)) {
                $logoData = base64_encode(file_get_contents($logoFile));
                $logoMime = mime_content_type($logoFile) ?: 'image/png';
                $logoSrc  = 'data:' . $logoMime . ';base64,' . $logoData;
            } else {
                $logoSrc = LOGO_PUBLIC_URL . basename($invoice['business_logo_path_snapshot']);
            }
            $html .= '<img src="' . $logoSrc . '" alt="Logo" style="max-height: 60px; margin-bottom: 15px;"><br>';
        }
        $html .= '<h2>' . htmlspecialchars($invoice['business_name_snapshot']) . '</h2>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="invoice-header">';
        $html .= '<h1>INVOICE</h1>';
        $html .= '<div class="invoice-details">';
        $html .= '<p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('M d, Y', strtotime($invoice['issue_date'])) . '</p>';
        $html .= '<p><strong>Due Date:</strong> ' . date('M d, Y', strtotime($invoice['due_date'])) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p><strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong></p>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= '<p>' . htmlspecialchars($invoice['client_company_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '</p>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= '<p>Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= '<p>Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Qty</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><th>Subtotal:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><th>Tax Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Total Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Paid Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Balance Due:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Notes:</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '<div class="footer">';
        $html .= '<p>Thank you for your business!</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    private function generateElegantTemplate($invoice, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { 
                    font-family: "Georgia", "Times New Roman", serif; 
                    margin: 0; 
                    padding: 40px; 
                    color: #34495e;
                    background: linear-gradient(to bottom, #f9f9f9, #ffffff);
                }
                .container { 
                    max-width: 800px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 50px; 
                    border-radius: 10px;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                }
                .header { 
                    display: flex; 
                    justify-content: space-between; 
                    margin-bottom: 40px;
                    padding-bottom: 25px;
                    border-bottom: 1px solid #ecf0f1;
                    position: relative;
                }
                .header::after {
                    content: "";
                    position: absolute;
                    bottom: -1px;
                    left: 0;
                    right: 0;
                    height: 2px;
                    background: linear-gradient(to right, #bdc3c7, #ecf0f1, #bdc3c7);
                }
                .company-info h2 { 
                    margin: 0; 
                    font-weight: bold; 
                    color: #34495e;
                    font-size: 26px;
                    letter-spacing: 1px;
                }
                .company-info p { 
                    margin: 10px 0 0 0; 
                    color: #7f8c8d; 
                    font-size: 14px;
                    line-height: 1.6;
                }
                .invoice-header { 
                    text-align: right; 
                }
                .invoice-header h1 { 
                    margin: 0; 
                    font-size: 38px; 
                    font-weight: bold; 
                    color: #34495e;
                    letter-spacing: 2px;
                    font-family: "Palatino Linotype", "Book Antiqua", Palatino, serif;
                }
                .invoice-details { 
                    margin: 20px 0 0 0; 
                    font-size: 15px; 
                    line-height: 1.7;
                }
                .client-info { 
                    margin: 35px 0; 
                    padding: 25px;
                    background: linear-gradient(to right, #f8f9fa, #ffffff);
                    border: 1px solid #ecf0f1;
                    border-radius: 8px;
                    box-shadow: inset 0 0 10px rgba(0,0,0,0.03);
                }
                .client-info h3 { 
                    margin: 0 0 15px 0; 
                    font-weight: bold;
                    color: #34495e;
                    font-size: 18px;
                    position: relative;
                    padding-bottom: 10px;
                }
                .client-info h3::after {
                    content: "";
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 50px;
                    height: 2px;
                    background-color: #bdc3c7;
                }
                .client-info p { 
                    margin: 10px 0; 
                    font-size: 14px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 35px 0;
                    font-size: 14px;
                    border-radius: 5px;
                    overflow: hidden;
                    box-shadow: 0 0 10px rgba(0,0,0,0.05);
                }
                th { 
                    background: linear-gradient(to bottom, #34495e, #2c3e50); 
                    color: white;
                    padding: 16px;
                    text-align: left;
                    font-weight: normal;
                    font-style: italic;
                }
                td { 
                    padding: 16px; 
                    border-bottom: 1px solid #ecf0f1;
                }
                tr:nth-child(even) { 
                    background-color: #f8f9fa; 
                }
                tr:last-child td { border-bottom: none; }
                .totals { 
                    margin-left: auto; 
                    width: 300px; 
                    margin-top: 35px;
                    background: linear-gradient(to right, #f8f9fa, #ffffff);
                    border: 1px solid #ecf0f1;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.03);
                }
                .totals table { 
                    border: none;
                    width: 100%;
                }
                .totals th, .totals td { 
                    border: none; 
                    padding: 10px 0;
                    text-align: right;
                }
                .footer { 
                    margin-top: 50px; 
                    padding-top: 30px; 
                    border-top: 1px solid #ecf0f1;
                    text-align: center;
                    color: #7f8c8d;
                    font-size: 14px;
                    font-style: italic;
                }
            </style>
        </head>
        <body>
        <div class="container">';

        $html .= '<div class="header">';
        
        $html .= '<div class="company-info">';
        if (!empty($invoice['business_logo_path_snapshot'])) {
            $logoPath = $invoice['business_logo_path_snapshot'];
            $html .= '<img src="' . $logoPath . '" alt="Logo" style="max-height: 65px; margin-bottom: 15px;"><br>';
        }
        $html .= '<h2>' . htmlspecialchars($invoice['business_name_snapshot']) . '</h2>';
        if (!empty($invoice['business_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['business_address_snapshot'])) . '</p>';
        }
        $html .= '</div>';

        $html .= '<div class="invoice-header">';
        $html .= '<h1>INVOICE</h1>';
        $html .= '<div class="invoice-details">';
        $html .= '<p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('F d, Y', strtotime($invoice['issue_date'])) . '</p>';
        $html .= '<p><strong>Due Date:</strong> ' . date('F d, Y', strtotime($invoice['due_date'])) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        $html .= '<div class="client-info">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p><strong>' . htmlspecialchars($invoice['client_name_snapshot']) . '</strong></p>';
        if (!empty($invoice['client_company_snapshot'])) {
            $html .= '<p>' . htmlspecialchars($invoice['client_company_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_address_snapshot'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['client_address_snapshot'])) . '</p>';
        }
        if (!empty($invoice['client_email_snapshot'])) {
            $html .= '<p>Email: ' . htmlspecialchars($invoice['client_email_snapshot']) . '</p>';
        }
        if (!empty($invoice['client_phone_snapshot'])) {
            $html .= '<p>Phone: ' . htmlspecialchars($invoice['client_phone_snapshot']) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr><th>Description</th><th>Quantity</th><th>Rate</th><th>Tax %</th><th>Total</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td>' . number_format($item['quantity'], 2) . '</td>';
            $html .= '<td>' . number_format($item['rate'], 2) . '</td>';
            $html .= '<td>' . number_format($item['tax_percent'], 2) . '%</td>';
            $html .= '<td>' . number_format($item['line_total'], 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<table>';
        $html .= '<tr><th>Subtotal:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['subtotal'], 2) . '</td></tr>';
        $html .= '<tr><th>Tax Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['tax_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Total Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Paid Amount:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '<tr><th>Balance Due:</th><td>' . htmlspecialchars($invoice['currency']) . ' ' . number_format($invoice['total_amount'] - $invoice['paid_amount'], 2) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="notes" style="margin-top: 30px;">';
            $html .= '<h4>Additional Notes:</h4>';
            $html .= '<p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>';
            $html .= '</div>';
        }

        $html .= '<div class="footer">';
        $html .= '<p>We appreciate your business. Thank you for your patronage.</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }
}
