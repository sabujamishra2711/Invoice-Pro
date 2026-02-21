<?php
require_once __DIR__ . '/../services/ExpenseService.php';

class ExpenseController
{
    public function list($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $filters = [
                'category' => $_GET['category'] ?? null,
                'client_id' => $_GET['client_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'is_billable' => $_GET['is_billable'] ?? null,
                'unbilled' => $_GET['unbilled'] ?? null,
            ];

            $expenses = $service->list($userId, array_filter($filters));
            return ['success' => true, 'data' => ['expenses' => $expenses]];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function get($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                return ['success' => false, 'message' => 'Expense ID is required', 'http_code' => 400];
            }

            $expense = $service->get((int)$id, $userId);
            if (!$expense) {
                return ['success' => false, 'message' => 'Expense not found', 'http_code' => 404];
            }
            return ['success' => true, 'data' => $expense];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function create($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $required = ['category', 'amount', 'expense_date'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    return ['success' => false, 'message' => "Field '$field' is required", 'http_code' => 400];
                }
            }

            $expense = $service->create($userId, $input);
            return ['success' => true, 'data' => $expense, 'message' => 'Expense created', 'http_code' => 201];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function update($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                return ['success' => false, 'message' => 'Expense ID is required', 'http_code' => 400];
            }

            $expense = $service->update((int)$id, $userId, $input);
            if (!$expense) {
                return ['success' => false, 'message' => 'Expense not found', 'http_code' => 404];
            }
            return ['success' => true, 'data' => $expense, 'message' => 'Expense updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function delete($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                return ['success' => false, 'message' => 'Expense ID is required', 'http_code' => 400];
            }

            $deleted = $service->delete((int)$id, $userId);
            if (!$deleted) {
                return ['success' => false, 'message' => 'Expense not found', 'http_code' => 404];
            }
            return ['success' => true, 'message' => 'Expense deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function uploadReceipt($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                return ['success' => false, 'message' => 'Expense ID is required', 'http_code' => 400];
            }

            if (empty($_FILES['receipt'])) {
                return ['success' => false, 'message' => 'No file uploaded', 'http_code' => 400];
            }

            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                return ['success' => false, 'message' => 'Invalid file type. Allowed: jpg, png, gif, pdf', 'http_code' => 400];
            }

            $expense = $service->uploadReceipt((int)$id, $userId, $_FILES['receipt']);
            if (!$expense) {
                return ['success' => false, 'message' => 'Expense not found', 'http_code' => 404];
            }
            return ['success' => true, 'data' => $expense, 'message' => 'Receipt uploaded'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function deleteReceipt($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                return ['success' => false, 'message' => 'Expense ID is required', 'http_code' => 400];
            }

            $deleted = $service->deleteReceipt((int)$id, $userId);
            if (!$deleted) {
                return ['success' => false, 'message' => 'Receipt not found', 'http_code' => 404];
            }
            return ['success' => true, 'message' => 'Receipt deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function summary($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            $summary = $service->getSummary($userId, $dateFrom, $dateTo);
            $byCategory = $service->getByCategory($userId, $dateFrom, $dateTo);

            return [
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'by_category' => $byCategory
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function listCategories($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $categories = $service->listCategories($userId);
            return ['success' => true, 'data' => ['categories' => $categories]];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function createCategory($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            if (empty($input['name'])) {
                return ['success' => false, 'message' => 'Category name is required', 'http_code' => 400];
            }

            $category = $service->createCategory($userId, $input['name'], $input['color'] ?? '#6366f1');
            return ['success' => true, 'data' => $category, 'http_code' => 201];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }

    public function deleteCategory($input): array
    {
        try {
            $userId = authenticateRequest();
            $db = getDB();
            $service = new ExpenseService($db);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                return ['success' => false, 'message' => 'Category ID is required', 'http_code' => 400];
            }

            $deleted = $service->deleteCategory($userId, (int)$id);
            if (!$deleted) {
                return ['success' => false, 'message' => 'Category not found', 'http_code' => 404];
            }
            return ['success' => true, 'message' => 'Category deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 500];
        }
    }
}
