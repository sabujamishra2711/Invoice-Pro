<?php

class RecurringInvoiceController
{
    private function svc($input)
    {
        $userId = authenticateRequest();
        require_once __DIR__ . '/../services/RecurringInvoiceService.php';
        return new RecurringInvoiceService((int)$userId);
    }

    // GET recurring.list
    public function list($input)
    {
        try {
            $rows = $this->svc($input)->list();
            return ['success' => true, 'data' => ['recurring_invoices' => $rows]];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    // GET recurring.get&id=X
    public function get($input)
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) return ['success' => false, 'message' => 'ID required', 'http_code' => 400];
        try {
            $data = $this->svc($input)->get($id);
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // POST recurring.create
    public function create($input)
    {
        try {
            $result = $this->svc($input)->create($input);
            return ['success' => true, 'data' => $result, 'http_code' => 201];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 404, 422]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // PUT recurring.update&id=X
    public function update($input)
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) return ['success' => false, 'message' => 'ID required', 'http_code' => 400];
        try {
            $result = $this->svc($input)->update($id, $input);
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404, 422]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // POST recurring.pause&id=X
    public function pause($input)
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) return ['success' => false, 'message' => 'ID required', 'http_code' => 400];
        try {
            $result = $this->svc($input)->pause($id);
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // POST recurring.resume&id=X
    public function resume($input)
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) return ['success' => false, 'message' => 'ID required', 'http_code' => 400];
        try {
            $result = $this->svc($input)->resume($id);
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // DELETE recurring.delete&id=X
    public function delete($input)
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) return ['success' => false, 'message' => 'ID required', 'http_code' => 400];
        try {
            $this->svc($input)->delete($id);
            return ['success' => true, 'data' => []];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // POST recurring.generate&id=X
    public function generateNow($input)
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) return ['success' => false, 'message' => 'ID required', 'http_code' => 400];
        try {
            $invoice = $this->svc($input)->generateNow($id);
            return ['success' => true, 'data' => $invoice, 'message' => 'Invoice generated successfully'];
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 403, 404]) ? $e->getCode() : 500;
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => $code];
        }
    }

    // POST recurring.process — cron endpoint (no user auth, secret-protected)
    public function processDue($input)
    {
        $secret = $_GET['secret'] ?? '';
        $expected = defined('CRON_SECRET') ? CRON_SECRET : 'invoicepro_cron';
        if ($secret !== $expected) {
            return ['success' => false, 'message' => 'Forbidden', 'http_code' => 403];
        }
        require_once __DIR__ . '/../services/RecurringInvoiceService.php';
        $results = RecurringInvoiceService::processDue();
        return ['success' => true, 'data' => ['processed' => count($results), 'results' => $results]];
    }
}
