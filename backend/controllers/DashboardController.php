<?php
// Dashboard Analytics Controller

class DashboardController
{
    private function getUserId()
    {
        return $GLOBALS['current_user_id'] ?? authenticateRequest();
    }

    public function get($input)
    {
        return $this->getStats($input);
    }

    public function getStats($input)
    {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return ['success' => false, 'error_code' => 'UNAUTHORIZED', 'message' => 'Not authenticated', 'http_code' => 401];
            }

            $db = getDB();

            // Total invoices count
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            $totalInvoices = (int)$stmt->fetch()['cnt'];

            // Total revenue (paid amounts)
            $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total FROM invoices WHERE user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            $totalRevenue = (float)$stmt->fetch()['total'];

            // Pending amount (outstanding)
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount - paid_amount), 0) as pending FROM invoices WHERE user_id = ? AND deleted_at IS NULL AND paid_amount < total_amount");
            $stmt->execute([$userId]);
            $pendingAmount = (float)$stmt->fetch()['pending'];

            // Total clients
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM clients WHERE user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            $totalClients = (int)$stmt->fetch()['cnt'];

            // Status counts
            $stmt = $db->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN paid_amount >= total_amount AND total_amount > 0 THEN 1 
                         WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                    SUM(CASE WHEN paid_amount > 0 AND paid_amount < total_amount THEN 1 
                         WHEN status = 'partial' THEN 1 ELSE 0 END) as partial,
                    SUM(CASE WHEN paid_amount < total_amount AND due_date < CURDATE() AND status != 'draft' THEN 1 
                         WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                    SUM(CASE WHEN status = 'sent' AND paid_amount = 0 AND due_date >= CURDATE() THEN 1 ELSE 0 END) as sent
                FROM invoices 
                WHERE user_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$userId]);
            $statusCounts = $stmt->fetch();

            // Monthly revenue — respect period filter
            $period = $_GET['period'] ?? '6m';
            switch ($period) {
                case '1y':
                    $interval = 12;
                    break;
                case 'all':
                    $interval = 120; // 10 years max
                    break;
                case '6m':
                default:
                    $interval = 6;
                    break;
            }

            $stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(issue_date, '%Y-%m') as month,
                    COALESCE(SUM(paid_amount), 0) as total
                FROM invoices 
                WHERE user_id = ? AND deleted_at IS NULL
                AND issue_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$userId, $interval]);
            $monthlyRevenue = $stmt->fetchAll();

            // Recent invoices (last 5)
            $stmt = $db->prepare("
                SELECT 
                    i.id, i.invoice_number, i.issue_date, i.due_date,
                    i.total_amount, i.paid_amount, i.status,
                    c.name as client_name, c.company as client_company
                FROM invoices i
                JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ? AND i.deleted_at IS NULL
                ORDER BY i.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $recentInvoices = $stmt->fetchAll();

            // Top clients by revenue
            $stmt = $db->prepare("
                SELECT 
                    c.name, c.company,
                    COALESCE(SUM(i.paid_amount), 0) as revenue,
                    COUNT(i.id) as invoice_count
                FROM clients c
                LEFT JOIN invoices i ON c.id = i.client_id AND i.deleted_at IS NULL
                WHERE c.user_id = ? AND c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.company
                HAVING revenue > 0
                ORDER BY revenue DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $topClients = $stmt->fetchAll();

            return [
                'success' => true,
                'data' => [
                    'total_invoices' => $totalInvoices,
                    'total_revenue' => $totalRevenue,
                    'pending_amount' => $pendingAmount,
                    'total_clients' => $totalClients,
                    'status_counts' => [
                        'draft'   => (int)($statusCounts['draft'] ?? 0),
                        'sent'    => (int)($statusCounts['sent'] ?? 0),
                        'partial' => (int)($statusCounts['partial'] ?? 0),
                        'paid'    => (int)($statusCounts['paid'] ?? 0),
                        'overdue' => (int)($statusCounts['overdue'] ?? 0),
                    ],
                    'monthly_revenue' => $monthlyRevenue,
                    'recent_invoices' => $recentInvoices,
                    'top_clients' => $topClients
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'DASHBOARD_STATS_FAILED',
                'message' => 'Failed to fetch dashboard stats: ' . $e->getMessage(),
                'http_code' => 500
            ];
        }
    }
}
