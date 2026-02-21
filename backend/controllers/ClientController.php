<?php
// Client Management Controller

require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/TierGuard.php';

class ClientController
{

    public function list($input)
    {
        try {
            $userId = authenticateRequest();

            $db = getDB();

            // Get all clients for user with financial summary
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    COALESCE(SUM(i.total_amount), 0) as total_billed,
                    COALESCE(SUM(i.paid_amount), 0) as total_paid,
                    COALESCE(SUM(i.total_amount - i.paid_amount), 0) as outstanding
                FROM clients c
                LEFT JOIN invoices i ON c.id = i.client_id AND i.user_id = ?
                WHERE c.user_id = ?
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$userId, $userId]);
            $clients = $stmt->fetchAll();

            return [
                'success' => true,
                'data' => ['clients' => $clients]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'CLIENT_LIST_FAILED',
                'message' => 'Failed to fetch clients',
                'http_code' => 500
            ];
        }
    }

    public function create($input)
    {
        try {
            $userId = authenticateRequest();

            // ── Tier limit check ──
            try {
                TierGuard::assertCanCreateClient($userId);
            } catch (LimitException $e) {
                return [
                    'success'    => false,
                    'error_code' => 'LIMIT_REACHED',
                    'resource'   => $e->resource,
                    'current'    => $e->current,
                    'limit'      => $e->limit,
                    'plan'       => $e->plan,
                    'message'    => $e->getMessage(),
                    'http_code'  => 403
                ];
            }

            // Validate input
            $validation = Validator::validateClientData($input);
            if ($validation !== true) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validation],
                    'http_code' => 400
                ];
            }

            $db = getDB();

            $stmt = $db->prepare("
                INSERT INTO clients (user_id, name, email, phone, company, address, gst_number) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $input['name'],
                $input['email'] ?? null,
                $input['phone'] ?? null,
                $input['company'] ?? null,
                $input['address'] ?? null,
                $input['gst_number'] ?? null
            ]);

            $clientId = $db->lastInsertId();

            // Return the created client
            $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();

            return [
                'success' => true,
                'data' => ['client' => $client],
                'message' => 'Client created successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'CLIENT_CREATE_FAILED',
                'message' => 'Failed to create client',
                'http_code' => 500
            ];
        }
    }

    public function get($input)
    {
        try {
            $userId = authenticateRequest();
            $clientId = $_GET['id'] ?? null;

            if (!$clientId) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Client ID is required',
                    'http_code' => 400
                ];
            }

            $db = getDB();

            // Get client details with financial summary
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    COALESCE(SUM(i.total_amount), 0) as total_billed,
                    COALESCE(SUM(i.paid_amount), 0) as total_paid,
                    COALESCE(SUM(i.total_amount - i.paid_amount), 0) as outstanding
                FROM clients c
                LEFT JOIN invoices i ON c.id = i.client_id AND i.user_id = ?
                WHERE c.id = ? AND c.user_id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$userId, $clientId, $userId]);
            $client = $stmt->fetch();

            if (!$client) {
                return [
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Client not found',
                    'http_code' => 404
                ];
            }

            // Get client's invoices with calculated status
            $stmt = $db->prepare("
                SELECT 
                    id, invoice_number, issue_date, due_date, total_amount, 
                    paid_amount, created_at,
                    CASE 
                        WHEN paid_amount >= total_amount THEN 'paid'
                        WHEN paid_amount > 0 THEN 'partial'
                        WHEN paid_amount < total_amount AND due_date < CURDATE() THEN 'overdue'
                        ELSE 'sent'
                    END as calculated_status
                FROM invoices 
                WHERE client_id = ? AND user_id = ? AND deleted_at IS NULL
                ORDER BY created_at DESC
            ");
            $stmt->execute([$clientId, $userId]);
            $invoices = $stmt->fetchAll();

            return [
                'success' => true,
                'data' => [
                    'client' => $client,
                    'invoices' => $invoices
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'CLIENT_GET_FAILED',
                'message' => 'Failed to fetch client details',
                'http_code' => 500
            ];
        }
    }

    public function update($input)
    {
        try {
            $userId = authenticateRequest();
            $clientId = $_GET['id'] ?? null;

            if (!$clientId) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Client ID is required',
                    'http_code' => 400
                ];
            }

            // Validate input
            $validation = Validator::validateClientData($input);
            if ($validation !== true) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validation],
                    'http_code' => 400
                ];
            }

            $db = getDB();

            // Update the client
            $stmt = $db->prepare("
                UPDATE clients 
                SET name = ?, email = ?, phone = ?, company = ?, address = ?, gst_number = ?
                WHERE id = ? AND user_id = ?
            ");

            $result = $stmt->execute([
                $input['name'],
                $input['email'] ?? null,
                $input['phone'] ?? null,
                $input['company'] ?? null,
                $input['address'] ?? null,
                $input['gst_number'] ?? null,
                $clientId,
                $userId
            ]);

            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Client not found or not owned by user',
                    'http_code' => 404
                ];
            }

            // Return the updated client
            $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();

            return [
                'success' => true,
                'data' => ['client' => $client],
                'message' => 'Client updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'CLIENT_UPDATE_FAILED',
                'message' => 'Failed to update client',
                'http_code' => 500
            ];
        }
    }

    public function delete($input)
    {
        try {
            $userId = $GLOBALS['current_user_id'] ?? authenticateRequest();
            $clientId = $_GET['id'] ?? null;

            if (!$clientId) {
                return [
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Client ID is required',
                    'http_code' => 400
                ];
            }

            $db = getDB();

            // Soft delete
            $stmt = $db->prepare("UPDATE clients SET deleted_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$clientId, $userId]);

            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Client not found',
                    'http_code' => 404
                ];
            }

            return [
                'success' => true,
                'data' => [],
                'message' => 'Client deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'CLIENT_DELETE_FAILED',
                'message' => 'Failed to delete client',
                'http_code' => 500
            ];
        }
    }
}
