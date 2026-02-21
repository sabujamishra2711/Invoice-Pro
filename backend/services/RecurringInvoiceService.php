<?php

class RecurringInvoiceService
{
    private $db;
    private $userId;

    public function __construct($userId)
    {
        $this->db = getDB();
        $this->userId = (int)$userId;
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function list()
    {
        $stmt = $this->db->prepare("
            SELECT ri.*,
                   c.name  AS client_name,
                   c.email AS client_email,
                   c.company AS client_company,
                   (SELECT COUNT(*) FROM invoices i
                    WHERE i.user_id = ri.user_id
                      AND i.notes LIKE CONCAT('%[recurring:', ri.id, ']%')) AS generated_count
            FROM recurring_invoices ri
            JOIN clients c ON c.id = ri.client_id
            WHERE ri.user_id = ?
            ORDER BY ri.created_at DESC
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }

    public function get($id)
    {
        $stmt = $this->db->prepare("
            SELECT ri.*,
                   c.name  AS client_name,
                   c.email AS client_email,
                   c.company AS client_company
            FROM recurring_invoices ri
            JOIN clients c ON c.id = ri.client_id
            WHERE ri.id = ? AND ri.user_id = ?
        ");
        $stmt->execute([$id, $this->userId]);
        $ri = $stmt->fetch();
        if (!$ri) throw new Exception('Recurring invoice not found', 404);

        $ri['items'] = $this->getItems($id);
        return $ri;
    }

    private function getItems($recurringId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM recurring_invoice_items
            WHERE recurring_invoice_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$recurringId]);
        return $stmt->fetchAll();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function create($data)
    {
        $this->validateCreateData($data);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO recurring_invoices
                    (user_id, client_id, title, frequency, next_date, end_date, currency, notes, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $this->userId,
                (int)$data['client_id'],
                trim($data['title']),
                $data['frequency'],
                $data['next_date'],
                $data['end_date'] ?: null,
                $data['currency'] ?? 'INR',
                $data['notes'] ?? null
            ]);
            $id = (int)$this->db->lastInsertId();

            $this->insertItems($id, $data['items']);
            $this->db->commit();
            return $this->get($id);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function update($id, $data)
    {
        $existing = $this->get($id); // throws 404 if not found/not owned

        $this->validateCreateData($data);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE recurring_invoices
                SET client_id = ?, title = ?, frequency = ?, next_date = ?,
                    end_date = ?, currency = ?, notes = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                (int)$data['client_id'],
                trim($data['title']),
                $data['frequency'],
                $data['next_date'],
                $data['end_date'] ?: null,
                $data['currency'] ?? 'INR',
                $data['notes'] ?? null,
                $id,
                $this->userId
            ]);

            // Replace items
            $this->db->prepare("DELETE FROM recurring_invoice_items WHERE recurring_invoice_id = ?")->execute([$id]);
            $this->insertItems($id, $data['items']);

            $this->db->commit();
            return $this->get($id);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Pause / Resume ───────────────────────────────────────────────────────

    public function pause($id)
    {
        $this->get($id);
        $this->db->prepare("UPDATE recurring_invoices SET is_active = 0 WHERE id = ? AND user_id = ?")
                 ->execute([$id, $this->userId]);
        return $this->get($id);
    }

    public function resume($id)
    {
        $this->get($id);
        $this->db->prepare("UPDATE recurring_invoices SET is_active = 1 WHERE id = ? AND user_id = ?")
                 ->execute([$id, $this->userId]);
        return $this->get($id);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function delete($id)
    {
        $this->get($id);
        $this->db->prepare("DELETE FROM recurring_invoices WHERE id = ? AND user_id = ?")
                 ->execute([$id, $this->userId]);
        return true;
    }

    // ── Generate Now (manual trigger for one record) ─────────────────────────

    public function generateNow($id)
    {
        $ri = $this->get($id);
        if (!$ri['is_active']) throw new Exception('Recurring invoice is paused', 400);
        return $this->generateInvoiceFrom($ri);
    }

    // ── Process All Due (cron / batch trigger) ───────────────────────────────

    /**
     * Called by the cron endpoint. Processes ALL active recurring invoices
     * across ALL users whose next_date <= today.
     * Returns array of results.
     */
    public static function processDue()
    {
        $db = getDB();
        $today = date('Y-m-d');

        $stmt = $db->prepare("
            SELECT ri.*
            FROM recurring_invoices ri
            WHERE ri.is_active = 1
              AND ri.next_date <= ?
              AND (ri.end_date IS NULL OR ri.end_date >= ?)
            ORDER BY ri.id ASC
        ");
        $stmt->execute([$today, $today]);
        $due = $stmt->fetchAll();

        $results = [];
        foreach ($due as $ri) {
            try {
                $svc = new self($ri['user_id']);
                $invoice = $svc->generateInvoiceFrom($ri);
                $results[] = ['id' => $ri['id'], 'status' => 'ok', 'invoice_id' => $invoice['id']];
            } catch (Exception $e) {
                $results[] = ['id' => $ri['id'], 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        return $results;
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    private function generateInvoiceFrom(array $ri)
    {
        require_once __DIR__ . '/InvoiceService.php';
        require_once __DIR__ . '/InvoiceNumberGenerator.php';

        $items = $this->getItems($ri['id']);

        // Build invoice data
        $gen = new InvoiceNumberGenerator($ri['user_id']);
        $invoiceNumber = $gen->generate();

        $today = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+30 days'));

        // Compute subtotal / tax / total from items
        $subtotal = 0;
        $taxAmount = 0;
        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['rate'];
            $subtotal += $lineTotal;
            $taxAmount += $lineTotal * ($item['tax_percent'] / 100);
        }
        $total = $subtotal + $taxAmount;

        $invoiceData = [
            'client_id'      => $ri['client_id'],
            'invoice_number' => $invoiceNumber,
            'issue_date'     => $today,
            'due_date'       => $dueDate,
            'currency'       => $ri['currency'],
            'notes'          => ($ri['notes'] ? $ri['notes'] . "\n" : '') . '[recurring:' . $ri['id'] . ']',
            'status'         => 'draft',
            'items'          => array_map(function($item) {
                return [
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'rate'        => $item['rate'],
                    'tax_percent' => $item['tax_percent']
                ];
            }, $items)
        ];

        $invoiceService = new InvoiceService($ri['user_id']);
        $invoice = $invoiceService->createInvoice($invoiceData);

        // Advance next_date and update counters
        $this->db->prepare("
            UPDATE recurring_invoices
            SET last_generated   = ?,
                invoices_created = invoices_created + 1,
                next_date        = ?,
                is_active        = CASE
                    WHEN end_date IS NOT NULL AND ? > end_date THEN 0
                    ELSE is_active
                END
            WHERE id = ?
        ")->execute([
            $today,
            $this->computeNextDate($ri['next_date'], $ri['frequency']),
            $this->computeNextDate($ri['next_date'], $ri['frequency']),
            $ri['id']
        ]);

        return $invoice;
    }

    private function computeNextDate($fromDate, $frequency)
    {
        $map = [
            'weekly'    => '+7 days',
            'biweekly'  => '+14 days',
            'monthly'   => '+1 month',
            'quarterly' => '+3 months',
            'yearly'    => '+1 year',
        ];
        $offset = $map[$frequency] ?? '+1 month';
        return date('Y-m-d', strtotime($offset, strtotime($fromDate)));
    }

    private function insertItems($recurringId, array $items)
    {
        $stmt = $this->db->prepare("
            INSERT INTO recurring_invoice_items
                (recurring_invoice_id, description, quantity, rate, tax_percent)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            if (empty(trim($item['description'] ?? ''))) continue;
            $stmt->execute([
                $recurringId,
                trim($item['description']),
                max(0.01, (float)($item['quantity'] ?? 1)),
                max(0, (float)($item['rate'] ?? 0)),
                max(0, min(100, (float)($item['tax_percent'] ?? 0)))
            ]);
        }
    }

    private function validateCreateData($data)
    {
        if (empty($data['client_id'])) throw new Exception('Client is required', 400);
        if (empty($data['title']))     throw new Exception('Title is required', 400);
        if (empty($data['next_date'])) throw new Exception('Start date is required', 400);

        $validFreq = ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];
        if (!in_array($data['frequency'] ?? '', $validFreq)) {
            throw new Exception('Invalid frequency', 400);
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new Exception('At least one item is required', 400);
        }
        $hasItem = false;
        foreach ($data['items'] as $item) {
            if (!empty(trim($item['description'] ?? ''))) { $hasItem = true; break; }
        }
        if (!$hasItem) throw new Exception('At least one item is required', 400);
    }
}
