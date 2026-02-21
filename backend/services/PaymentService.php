<?php
// Payment Service - Handles all payment-related business logic with transactional integrity

class PaymentService
{
    private $db;
    private $invoiceService;

    public function __construct()
    {
        $this->db = getDB();
        $this->invoiceService = new InvoiceService();
    }

    // Add payment with full transactional integrity and concurrency protection
    public function addPayment($userId, $invoiceId, $amount, $paymentDate, $method, $reference = null, $idempotencyKey = null)
    {
        // If idempotency key is provided, check if it already exists
        if ($idempotencyKey !== null) {
            $existingResult = $this->checkIdempotencyKey($userId, $idempotencyKey);
            if ($existingResult !== false) {
                return $existingResult;
            }
        }

        try {
            // Start transaction
            $this->db->beginTransaction();

            // If idempotency key provided, attempt to insert it FIRST as the distributed lock
            // This ensures that only one request can proceed with the same idempotency key
            if ($idempotencyKey !== null) {
                try {
                    $this->insertIdempotencyKey($userId, $idempotencyKey, []);
                } catch (PDOException $e) {
                    // If we get a duplicate key error, it means another request is processing
                    // This is the atomic protection mechanism
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $this->db->rollback();
                        // Check if the other request has completed and return its result
                        $result = $this->checkIdempotencyKey($userId, $idempotencyKey);
                        if ($result !== false) {
                            return $result;
                        }
                        // If somehow the other request hasn't completed yet, return an error
                        return [
                            'success' => false,
                            'error_code' => 'IDEMPOTENCY_KEY_IN_USE',
                            'message' => 'Another request with the same idempotency key is being processed'
                        ];
                    } else {
                        // Re-throw if it's a different error
                        throw $e;
                    }
                }
            }

            // Verify invoice ownership and get current state WITH ROW LOCKING
            $stmt = $this->db->prepare("
                SELECT i.id, i.user_id, i.total_amount, i.paid_amount
                FROM invoices i 
                WHERE i.id = ? AND i.user_id = ? AND i.deleted_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$invoiceId, $userId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                $this->db->rollback();
                $result = [
                    'success' => false,
                    'error_code' => 'INVOICE_NOT_FOUND',
                    'message' => 'Invoice not found or access denied'
                ];

                // If idempotency key was used, update the stored response
                if ($idempotencyKey !== null) {
                    $this->updateIdempotencyResponse($userId, $idempotencyKey, $result);
                }

                return $result;
            }

            // Validate payment amount
            $validation = $this->invoiceService->validatePaymentAmount($invoiceId, $amount);
            if (!$validation['valid']) {
                $this->db->rollback();
                $result = [
                    'success' => false,
                    'error_code' => 'INVALID_PAYMENT_AMOUNT',
                    'message' => $validation['message']
                ];

                // If idempotency key was used, update the stored response
                if ($idempotencyKey !== null) {
                    $this->updateIdempotencyResponse($userId, $idempotencyKey, $result);
                }

                return $result;
            }

            // Insert payment record
            $stmt = $this->db->prepare("
                INSERT INTO payments (user_id, invoice_id, amount, payment_date, method, reference) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $invoiceId, $amount, $paymentDate, $method, $reference]);
            $paymentId = $this->db->lastInsertId();

            // Recalculate paid amount from all payments
            $newPaidAmount = $this->invoiceService->recalculatePaidAmount($invoiceId);

            // Recalculate and update invoice status
            $newStatus = $this->invoiceService->calculateInvoiceStatus($invoiceId);

            // Mark PDF as dirty since payment status changed
            $stmt = $this->db->prepare("UPDATE invoices SET pdf_dirty = 1 WHERE id = ?");
            $stmt->execute([$invoiceId]);

            // Commit transaction
            $this->db->commit();

            // Return updated invoice data
            $updatedInvoice = $this->invoiceService->getInvoiceWithDetails($invoiceId, $userId);

            $result = [
                'success' => true,
                'data' => [
                    'payment_id' => $paymentId,
                    'invoice' => $updatedInvoice,
                    'new_status' => $newStatus
                ],
                'message' => 'Payment recorded successfully'
            ];

            // If idempotency key was used, update the stored response
            if ($idempotencyKey !== null) {
                $this->updateIdempotencyResponse($userId, $idempotencyKey, $result);
            }

            return $result;
        } catch (Exception $e) {
            // Rollback on any error
            $this->db->rollback();

            $result = [
                'success' => false,
                'error_code' => 'PAYMENT_FAILED',
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ];

            // If idempotency key was used and we have a valid database connection, try to update the stored response
            // Note: This might fail if the error occurred before the transaction started
            if ($idempotencyKey !== null) {
                try {
                    $this->updateIdempotencyResponse($userId, $idempotencyKey, $result);
                } catch (Exception $updateException) {
                    // If we can't update the idempotency response due to the same error, 
                    // we just continue as the main operation already failed
                }
            }

            return $result;
        }
    }

    // Check if idempotency key already exists for this user
    private function checkIdempotencyKey($userId, $idempotencyKey)
    {
        $stmt = $this->db->prepare("
            SELECT response_data 
            FROM idempotency_keys 
            WHERE user_id = ? AND idempotency_key = ?
        ");
        $stmt->execute([$userId, $idempotencyKey]);
        $result = $stmt->fetch();

        if ($result) {
            return json_decode($result['response_data'], true);
        }

        return false;
    }

    // Insert a new idempotency key (acts as distributed lock)
    private function insertIdempotencyKey($userId, $idempotencyKey, $responseData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO idempotency_keys (user_id, idempotency_key, response_data)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $idempotencyKey, json_encode($responseData)]);
    }

    // Update the response data for an existing idempotency key
    private function updateIdempotencyResponse($userId, $idempotencyKey, $responseData)
    {
        $stmt = $this->db->prepare("
            UPDATE idempotency_keys 
            SET response_data = ? 
            WHERE user_id = ? AND idempotency_key = ?
        ");
        $stmt->execute([json_encode($responseData), $userId, $idempotencyKey]);
    }

    // Get payment history for an invoice
    public function getInvoicePayments($userId, $invoiceId)
    {
        $stmt = $this->db->prepare("
            SELECT p.* 
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            WHERE p.invoice_id = ? AND i.user_id = ? AND i.deleted_at IS NULL
            ORDER BY p.payment_date DESC, p.created_at DESC
        ");
        $stmt->execute([$invoiceId, $userId]);
        return $stmt->fetchAll();
    }

    // Get all payments for user with filtering
    public function getUserPayments($userId, $filters = [])
    {
        $sql = "
            SELECT 
                p.*,
                i.invoice_number,
                i.total_amount,
                CASE 
                    WHEN i.paid_amount >= i.total_amount THEN 'paid'
                    WHEN i.paid_amount > 0 THEN 'partial'
                    WHEN i.paid_amount < i.total_amount AND i.due_date < CURDATE() THEN 'overdue'
                    ELSE 'sent'
                END as invoice_status,
                c.name as client_name
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            JOIN clients c ON i.client_id = c.id
            WHERE p.user_id = ? AND i.deleted_at IS NULL
        ";

        $params = [$userId];

        // Apply filters
        if (!empty($filters['date_from'])) {
            $sql .= " AND p.payment_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND p.payment_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['method'])) {
            $sql .= " AND p.method = ?";
            $params[] = $filters['method'];
        }

        $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
