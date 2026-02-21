<?php

class RecurringInvoiceController
{
    private function userId()
    {
        $user = getCurrentUser();
        if (!$user) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
        return (int)$user['id'];
    }

    private function svc()
    {
        require_once __DIR__ . '/../services/RecurringInvoiceService.php';
        return new RecurringInvoiceService($this->userId());
    }

    private function jsonBody()
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    // GET recurring.list
    public function list()
    {
        try {
            $rows = $this->svc()->list();
            echo json_encode(['success' => true, 'data' => ['recurring_invoices' => $rows]]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // GET recurring.get&id=X
    public function get()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID required']); return; }
        try {
            $data = $this->svc()->get($id);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $code = $e->getCode() ?: 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // POST recurring.create
    public function create()
    {
        try {
            $data = $this->jsonBody();
            $result = $this->svc()->create($data);
            http_response_code(201);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404, 422]) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // PUT recurring.update&id=X
    public function update()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID required']); return; }
        try {
            $data = $this->jsonBody();
            $result = $this->svc()->update($id, $data);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404, 422]) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // POST recurring.pause&id=X
    public function pause()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID required']); return; }
        try {
            $result = $this->svc()->pause($id);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // POST recurring.resume&id=X
    public function resume()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID required']); return; }
        try {
            $result = $this->svc()->resume($id);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // DELETE recurring.delete&id=X
    public function delete()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID required']); return; }
        try {
            $this->svc()->delete($id);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // POST recurring.generate&id=X  — generate invoice right now
    public function generateNow()
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID required']); return; }
        try {
            $invoice = $this->svc()->generateNow($id);
            echo json_encode(['success' => true, 'data' => $invoice, 'message' => 'Invoice generated successfully']);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404]) ? $e->getCode() : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // POST recurring.process  — cron: process all due (no auth required if secret matches)
    public function processDue()
    {
        // Simple secret check to protect cron endpoint
        $secret = $_GET['secret'] ?? '';
        $expected = defined('CRON_SECRET') ? CRON_SECRET : 'invoicepro_cron';
        if ($secret !== $expected) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        require_once __DIR__ . '/../services/RecurringInvoiceService.php';
        $results = RecurringInvoiceService::processDue();
        echo json_encode(['success' => true, 'data' => ['processed' => count($results), 'results' => $results]]);
    }
}
