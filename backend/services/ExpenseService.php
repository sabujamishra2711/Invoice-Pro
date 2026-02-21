<?php

class ExpenseService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function list(int $userId, array $filters = []): array
    {
        $sql = "SELECT e.*, c.name as client_name 
                FROM expenses e 
                LEFT JOIN clients c ON e.client_id = c.id 
                WHERE e.user_id = ?";
        $params = [$userId];

        if (!empty($filters['category'])) {
            $sql .= " AND e.category = ?";
            $params[] = $filters['category'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND e.client_id = ?";
            $params[] = $filters['client_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND e.expense_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND e.expense_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['is_billable'])) {
            $sql .= " AND e.is_billable = 1";
        }

        if (!empty($filters['unbilled'])) {
            $sql .= " AND e.is_billable = 1 AND e.invoice_id IS NULL";
        }

        $sql .= " ORDER BY e.expense_date DESC, e.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function get(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, c.name as client_name 
             FROM expenses e 
             LEFT JOIN clients c ON e.client_id = c.id 
             WHERE e.id = ? AND e.user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO expenses (user_id, category, description, amount, expense_date, vendor, payment_method, is_billable, client_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $data['category'],
            $data['description'] ?? null,
            $data['amount'],
            $data['expense_date'],
            $data['vendor'] ?? null,
            $data['payment_method'] ?? 'cash',
            $data['is_billable'] ?? 0,
            ($data['client_id'] ?? null) ?: null,
            $data['notes'] ?? null
        ]);

        return $this->get($this->db->lastInsertId(), $userId);
    }

    public function update(int $id, int $userId, array $data): ?array
    {
        $expense = $this->get($id, $userId);
        if (!$expense) {
            return null;
        }

        $stmt = $this->db->prepare(
            "UPDATE expenses SET 
                category = ?, description = ?, amount = ?, expense_date = ?, 
                vendor = ?, payment_method = ?, is_billable = ?, client_id = ?, notes = ?
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([
            $data['category'] ?? $expense['category'],
            $data['description'] ?? $expense['description'],
            $data['amount'] ?? $expense['amount'],
            $data['expense_date'] ?? $expense['expense_date'],
            $data['vendor'] ?? $expense['vendor'],
            $data['payment_method'] ?? $expense['payment_method'],
            $data['is_billable'] ?? $expense['is_billable'],
            array_key_exists('client_id', $data) ? ($data['client_id'] ?: null) : $expense['client_id'],
            $data['notes'] ?? $expense['notes'],
            $id,
            $userId
        ]);

        return $this->get($id, $userId);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function uploadReceipt(int $id, int $userId, array $file): ?array
    {
        $expense = $this->get($id, $userId);
        if (!$expense) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../storage/receipts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . $id . '_' . time() . '.' . $ext;
        $path = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new Exception('Failed to upload receipt');
        }

        // Delete old receipt if exists
        if ($expense['receipt_path'] && file_exists($uploadDir . basename($expense['receipt_path']))) {
            unlink($uploadDir . basename($expense['receipt_path']));
        }

        $stmt = $this->db->prepare("UPDATE expenses SET receipt_path = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$filename, $id, $userId]);

        return $this->get($id, $userId);
    }

    public function deleteReceipt(int $id, int $userId): bool
    {
        $expense = $this->get($id, $userId);
        if (!$expense || !$expense['receipt_path']) {
            return false;
        }

        $uploadDir = __DIR__ . '/../../storage/receipts/';
        $path = $uploadDir . basename($expense['receipt_path']);
        if (file_exists($path)) {
            unlink($path);
        }

        $stmt = $this->db->prepare("UPDATE expenses SET receipt_path = NULL WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return true;
    }

    public function getSummary(int $userId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_count,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN is_billable = 1 THEN amount ELSE 0 END), 0) as billable_amount,
                    COALESCE(SUM(CASE WHEN is_billable = 1 AND invoice_id IS NULL THEN amount ELSE 0 END), 0) as unbilled_amount
                FROM expenses WHERE user_id = ?";
        $params = [$userId];

        if ($dateFrom) {
            $sql .= " AND expense_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND expense_date <= ?";
            $params[] = $dateTo;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function getByCategory(int $userId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT category, COUNT(*) as count, SUM(amount) as total 
                FROM expenses WHERE user_id = ?";
        $params = [$userId];

        if ($dateFrom) {
            $sql .= " AND expense_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND expense_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " GROUP BY category ORDER BY total DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Category management
    public function listCategories(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM expense_categories WHERE user_id = ? ORDER BY name");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function createCategory(int $userId, string $name, string $color = '#6366f1'): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO expense_categories (user_id, name, color) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE color = VALUES(color)"
        );
        $stmt->execute([$userId, $name, $color]);

        $stmt = $this->db->prepare("SELECT * FROM expense_categories WHERE user_id = ? AND name = ?");
        $stmt->execute([$userId, $name]);
        return $stmt->fetch();
    }

    public function deleteCategory(int $userId, int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM expense_categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }
}
