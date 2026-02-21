<?php
// Invoice Business Logic Service
require_once __DIR__ . '/InvoiceNumberGenerator.php';

class InvoiceService
{
    private $db;
    private $objectStorage = null;

    public function __construct()
    {
        $this->db = getDB();
    }

    private function getObjectStorage()
    {
        if ($this->objectStorage === null) {
            if (file_exists(__DIR__ . '/ObjectStorageService.php')) {
                require_once __DIR__ . '/ObjectStorageService.php';
                $this->objectStorage = new ObjectStorageService();
            }
        }
        return $this->objectStorage;
    }


    // Calculate invoice status based on payments and due date (does not update DB)
    public function calculateInvoiceStatus($invoiceId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.total_amount,
                i.paid_amount,
                i.due_date,
                i.status
            FROM invoices i
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return false;
        }

        $total = $invoice['total_amount'];
        $paid = $invoice['paid_amount'];
        $dueDate = $invoice['due_date'];
        $currentStatus = $invoice['status'];

        // Calculate new status
        if ($currentStatus === 'draft') {
            $newStatus = 'draft';
        } elseif ($paid >= $total) {
            $newStatus = 'paid';
        } elseif ($paid > 0) {
            $newStatus = 'partial';
        } elseif (strtotime($dueDate) < strtotime('today')) {
            $newStatus = 'overdue';
        } else {
            $newStatus = 'sent';
        }

        // Do NOT update status in database anymore - keep for backward compatibility only
        // The status is now calculated on-the-fly for display purposes
        return $newStatus;
    }

    // Recalculate paid amount from payments
    public function recalculatePaidAmount($invoiceId)
    {
        // Get sum of all payments for this invoice
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_paid 
            FROM payments 
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch();

        $paidAmount = $result['total_paid'];

        // Update invoice paid amount
        $stmt = $this->db->prepare("UPDATE invoices SET paid_amount = ? WHERE id = ?");
        $stmt->execute([$paidAmount, $invoiceId]);

        return $paidAmount;
    }

    // Generate unique invoice number (deprecated - use InvoiceNumberGenerator)
    public function generateInvoiceNumber()
    {
        // This method is deprecated but kept for backward compatibility
        // Use InvoiceNumberGenerator service instead
        $generator = new InvoiceNumberGenerator(1); // Default user ID
        return $generator->generate();
    }

    // Validate payment amount doesn't exceed balance
    public function validatePaymentAmount($invoiceId, $paymentAmount)
    {
        $stmt = $this->db->prepare("
            SELECT total_amount, paid_amount 
            FROM invoices 
            WHERE id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return ['valid' => false, 'message' => 'Invoice not found'];
        }

        $balance = $invoice['total_amount'] - $invoice['paid_amount'];

        if ($paymentAmount > $balance) {
            return [
                'valid' => false,
                'message' => 'Payment amount exceeds invoice balance',
                'balance' => $balance
            ];
        }

        if ($paymentAmount <= 0) {
            return ['valid' => false, 'message' => 'Payment amount must be positive'];
        }

        return ['valid' => true, 'balance' => $balance];
    }

    // Get invoice with all related data and dynamic status recalculation
    public function getInvoiceWithDetails($invoiceId, $userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.*,
                c.name as client_name,
                c.company as client_company,
                c.email as client_email,
                c.phone as client_phone,
                c.address as client_address,
                c.gst_number as client_gst,
                u.name as user_name,
                u.email as user_email,
                s.business_name,
                s.address as business_address,
                s.gst_number as business_gst,
                s.default_tax
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            JOIN users u ON i.user_id = u.id
            LEFT JOIN settings s ON u.id = s.user_id
            WHERE i.id = ? AND i.user_id = ? AND i.deleted_at IS NULL
        ");
        $stmt->execute([$invoiceId, $userId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return false;
        }

        // Dynamically recalculate status based on current state
        $dynamicStatus = $this->calculateInvoiceStatus($invoiceId);
        $invoice['status'] = $dynamicStatus;

        return $invoice;
    }

    // Create invoice with all items in a single transaction
    public function createInvoice($userId, $clientId, $invoiceData)
    {
        try {
            $this->db->beginTransaction();

            // Get client and business details for snapshots
            $clientStmt = $this->db->prepare("
                SELECT name, company, email, phone, address, gst_number
                FROM clients
                WHERE id = ? AND user_id = ?
            ");
            $clientStmt->execute([$clientId, $userId]);
            $client = $clientStmt->fetch();

            if (!$client) {
                throw new Exception('Client not found or access denied');
            }

            $settingsStmt = $this->db->prepare("
                SELECT business_name, address, gst_number, logo_path
                FROM settings
                WHERE user_id = ?
            ");
            $settingsStmt->execute([$userId]);
            $settings = $settingsStmt->fetch();

            if (!$settings) {
                // If no settings exist, use default values
                $settings = [
                    'business_name' => '',
                    'address' => '',
                    'gst_number' => '',
                    'logo_path' => ''
                ];
            }

            // Simple logo path snapshot (no cloud upload needed for local deployment)
            $logoPathForSnapshot = $settings['logo_path'] ?? '';


            // Generate invoice number
            $generator = new InvoiceNumberGenerator($userId);
            $invoiceNumber = $generator->generate();

            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($invoiceData['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['rate'];
                $itemTax = $lineTotal * ($item['tax_percent'] / 100);

                $subtotal += $lineTotal;
                $taxAmount += $itemTax;
            }

            $totalAmount = $subtotal + $taxAmount;

            // Insert invoice with snapshots
            $stmt = $this->db->prepare("INSERT INTO invoices (
                user_id, client_id, invoice_number, issue_date, due_date,
                subtotal, tax_amount, total_amount, status, currency, notes, pdf_dirty,
                client_name_snapshot, client_company_snapshot, client_email_snapshot,
                client_phone_snapshot, client_address_snapshot, client_gst_snapshot,
                business_name_snapshot, business_address_snapshot, business_gst_snapshot,
                business_logo_path_snapshot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $issueDate = $invoiceData['issue_date'] ?? date('Y-m-d');
            $dueDate = $invoiceData['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
            $status = $invoiceData['status'] ?? 'draft';
            $currency = $invoiceData['currency'] ?? 'INR';

            $stmt->execute([
                $userId,
                $clientId,
                $invoiceNumber,
                $issueDate,
                $dueDate,
                $subtotal,
                $taxAmount,
                $totalAmount,
                $status,
                $currency,
                $invoiceData['notes'] ?? null,
                1, // pdf_dirty = true
                // Snapshots
                $client['name'],
                $client['company'],
                $client['email'],
                $client['phone'],
                $client['address'],
                $client['gst_number'],
                $settings['business_name'],
                $settings['address'],
                $settings['gst_number'],
                $logoPathForSnapshot // Use the object storage path or original path
            ]);

            $invoiceId = $this->db->lastInsertId();

            // Insert invoice items
            $stmt = $this->db->prepare("INSERT INTO invoice_items (
                invoice_id, description, quantity, rate, tax_percent, line_total
            ) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($invoiceData['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['rate'];
                $stmt->execute([
                    $invoiceId,
                    $item['description'],
                    $item['quantity'],
                    $item['rate'],
                    $item['tax_percent'] ?? 0,
                    $lineTotal
                ]);
            }

            // Commit transaction
            $this->db->commit();

            // Return the created invoice
            return $this->getInvoiceWithDetails($invoiceId, $userId);
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Update invoice with validation for locked invoices
    public function updateInvoice($invoiceId, $userId, $invoiceData)
    {
        try {
            $this->db->beginTransaction();

            // Get current invoice to check if it's locked
            $stmt = $this->db->prepare("
                SELECT i.*, 
                       CASE 
                           WHEN i.paid_amount >= i.total_amount THEN 'paid'
                           WHEN i.paid_amount > 0 THEN 'partial'
                           WHEN i.paid_amount < i.total_amount AND i.due_date < CURDATE() AND i.deleted_at IS NULL THEN 'overdue'
                           ELSE 'sent'
                       END as calculated_status
                FROM invoices i
                WHERE i.id = ? AND i.user_id = ? AND i.deleted_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$invoiceId, $userId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                $this->db->rollback();
                throw new Exception('Invoice not found or access denied');
            }

            // Check if invoice is locked (has payments)
            if ($invoice['paid_amount'] > 0) {
                throw new Exception("Invoice cannot be modified after payment is recorded.");
            }

            // Calculate new totals if items are provided
            $subtotal = $invoice['subtotal'];
            $taxAmount = $invoice['tax_amount'];
            $totalAmount = $invoice['total_amount'];

            if (isset($invoiceData['items'])) {
                // Calculate from new items
                $subtotal = 0;
                $taxAmount = 0;

                foreach ($invoiceData['items'] as $item) {
                    $lineTotal = $item['quantity'] * $item['rate'];
                    $itemTax = $lineTotal * ($item['tax_percent'] / 100);

                    $subtotal += $lineTotal;
                    $taxAmount += $itemTax;
                }

                $totalAmount = $subtotal + $taxAmount;

                // Validate that total is not less than paid amount
                if ($totalAmount < $invoice['paid_amount']) {
                    $this->db->rollback();
                    throw new Exception("Total cannot be less than already paid amount");
                }

                // Update invoice items
                // First delete existing items
                $stmt = $this->db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);

                // Then insert new items
                $stmt = $this->db->prepare("INSERT INTO invoice_items (
                    invoice_id, description, quantity, rate, tax_percent, line_total
                ) VALUES (?, ?, ?, ?, ?, ?)");

                foreach ($invoiceData['items'] as $item) {
                    $lineTotal = $item['quantity'] * $item['rate'];
                    $stmt->execute([
                        $invoiceId,
                        $item['description'],
                        $item['quantity'],
                        $item['rate'],
                        $item['tax_percent'] ?? 0,
                        $lineTotal
                    ]);
                }
            }

            // Prepare update data
            $updateFields = [];
            $updateParams = [];

            if (isset($invoiceData['issue_date'])) {
                $updateFields[] = "issue_date = ?";
                $updateParams[] = $invoiceData['issue_date'];
            }

            if (isset($invoiceData['due_date'])) {
                $updateFields[] = "due_date = ?";
                $updateParams[] = $invoiceData['due_date'];
            }

            if (isset($invoiceData['notes'])) {
                $updateFields[] = "notes = ?";
                $updateParams[] = $invoiceData['notes'];
            }

            if (isset($invoiceData['currency'])) {
                $updateFields[] = "currency = ?";
                $updateParams[] = $invoiceData['currency'];
            }

            if (!empty($updateFields)) {
                $updateQuery = "UPDATE invoices SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $updateParams[] = $invoiceId;

                $stmt = $this->db->prepare($updateQuery);
                $stmt->execute($updateParams);
            }

            // Update totals if items were modified
            if (isset($invoiceData['items'])) {
                $stmt = $this->db->prepare("
                    UPDATE invoices 
                    SET subtotal = ?, tax_amount = ?, total_amount = ?, pdf_dirty = 1
                    WHERE id = ?
                ");
                $stmt->execute([$subtotal, $taxAmount, $totalAmount, $invoiceId]);
            } else {
                // Mark PDF as dirty if other fields changed
                $stmt = $this->db->prepare("UPDATE invoices SET pdf_dirty = 1 WHERE id = ?");
                $stmt->execute([$invoiceId]);
            }

            // Recalculate status
            $newStatus = $this->calculateInvoiceStatus($invoiceId);

            $this->db->commit();

            // Return updated invoice
            return $this->getInvoiceWithDetails($invoiceId, $userId);
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Delete invoice with validation
    public function deleteInvoice($invoiceId, $userId)
    {
        try {
            $this->db->beginTransaction();

            // Get invoice to check if it has payments
            $stmt = $this->db->prepare("
                SELECT id, paid_amount, total_amount
                FROM invoices 
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$invoiceId, $userId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                $this->db->rollback();
                throw new Exception('Invoice not found or already deleted');
            }

            // Check if invoice has payments
            if ($invoice['paid_amount'] > 0) {
                $this->db->rollback();
                throw new Exception("Cannot delete invoice with recorded payments.");
            }

            // Soft delete the invoice
            $stmt = $this->db->prepare("UPDATE invoices SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$invoiceId]);

            $this->db->commit();

            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Duplicate an invoice — creates a new draft with today's dates and a new invoice number
    public function duplicateInvoice($invoiceId, $userId)
    {
        try {
            $this->db->beginTransaction();

            // Fetch the source invoice
            $stmt = $this->db->prepare("
                SELECT * FROM invoices
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$invoiceId, $userId]);
            $source = $stmt->fetch();

            if (!$source) {
                $this->db->rollback();
                throw new Exception('Invoice not found or access denied');
            }

            // Fetch source items
            $stmt = $this->db->prepare("
                SELECT description, quantity, rate, tax_percent, line_total
                FROM invoice_items WHERE invoice_id = ? ORDER BY id
            ");
            $stmt->execute([$invoiceId]);
            $sourceItems = $stmt->fetchAll();

            // Generate a new invoice number
            $generator = new InvoiceNumberGenerator($userId);
            $newNumber = $generator->generate();

            // New dates: today as issue_date, +30 days as due_date
            $issueDate = date('Y-m-d');
            $dueDate   = date('Y-m-d', strtotime('+30 days'));

            // Insert new invoice (draft, zero paid, fresh snapshots from source)
            $stmt = $this->db->prepare("INSERT INTO invoices (
                user_id, client_id, invoice_number, issue_date, due_date,
                subtotal, tax_amount, total_amount, status, currency, notes, pdf_dirty,
                client_name_snapshot, client_company_snapshot, client_email_snapshot,
                client_phone_snapshot, client_address_snapshot, client_gst_snapshot,
                business_name_snapshot, business_address_snapshot, business_gst_snapshot,
                business_logo_path_snapshot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, 1,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $userId,
                $source['client_id'],
                $newNumber,
                $issueDate,
                $dueDate,
                $source['subtotal'],
                $source['tax_amount'],
                $source['total_amount'],
                $source['currency'],
                $source['notes'],
                // Snapshots copied verbatim from source
                $source['client_name_snapshot'],
                $source['client_company_snapshot'],
                $source['client_email_snapshot'],
                $source['client_phone_snapshot'],
                $source['client_address_snapshot'],
                $source['client_gst_snapshot'],
                $source['business_name_snapshot'],
                $source['business_address_snapshot'],
                $source['business_gst_snapshot'],
                $source['business_logo_path_snapshot'],
            ]);

            $newId = $this->db->lastInsertId();

            // Copy line items
            $stmt = $this->db->prepare("INSERT INTO invoice_items
                (invoice_id, description, quantity, rate, tax_percent, line_total)
                VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($sourceItems as $item) {
                $stmt->execute([
                    $newId,
                    $item['description'],
                    $item['quantity'],
                    $item['rate'],
                    $item['tax_percent'],
                    $item['line_total'],
                ]);
            }

            $this->db->commit();

            return $this->getInvoiceWithDetails($newId, $userId);
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Compute display status based on financial conditions (centralized logic)
    public function getDisplayStatus($invoice)
    {
        if ($invoice['paid_amount'] >= $invoice['total_amount']) {
            return 'paid';
        } elseif ($invoice['paid_amount'] > 0) {
            return 'partial';
        } elseif (
            $invoice['paid_amount'] < $invoice['total_amount'] &&
            strtotime($invoice['due_date']) < strtotime('today') &&
            $invoice['deleted_at'] === null
        ) {
            return 'overdue';
        } else {
            return 'sent';
        }
    }

    // Check if invoice is locked for editing
    public function isInvoiceLocked($invoice)
    {
        return $invoice['paid_amount'] > 0;
    }
}
