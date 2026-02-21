<?php
// Dashboard Analytics Controller

class DashboardController
{
    private function getUserId()
    {
        return $GLOBALS['current_user_id'] ?? authenticateRequest();
    }

    /** Map period slug → SQL date condition fragment */
    private function periodCondition($period)
    {
        switch ($period) {
            case '30d':  return "AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            case '90d':  return "AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            case '1y':   return "AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            case 'all':
            default:     return "";  // no date restriction
        }
    }

    /** How many months back to show in the bar/line chart */
    private function periodMonths($period)
    {
        switch ($period) {
            case '30d': return 1;
            case '90d': return 3;
            case '1y':  return 12;
            case 'all': return 120;
            default:    return 6;
        }
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

            // Determine period
            $period = isset($_GET['period']) ? $_GET['period'] : '30d';
            $periodCond   = $this->periodCondition($period);
            $monthsBack   = $this->periodMonths($period);

            // ── Period-scoped stats ──

            // Total invoices in period
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM invoices WHERE user_id = ? AND deleted_at IS NULL $periodCond");
            $stmt->execute([$userId]);
            $totalInvoices = (int)$stmt->fetch()['cnt'];

            // Total revenue (paid) in period
            $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total FROM invoices WHERE user_id = ? AND deleted_at IS NULL $periodCond");
            $stmt->execute([$userId]);
            $totalRevenue = (float)$stmt->fetch()['total'];

            // Pending (outstanding) in period
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount - paid_amount), 0) as pending FROM invoices WHERE user_id = ? AND deleted_at IS NULL AND paid_amount < total_amount $periodCond");
            $stmt->execute([$userId]);
            $pendingAmount = (float)$stmt->fetch()['pending'];

            // Total billed (total_amount) in period — for avg invoice value
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as billed, COUNT(*) as cnt FROM invoices WHERE user_id = ? AND deleted_at IS NULL $periodCond");
            $stmt->execute([$userId]);
            $billedRow = $stmt->fetch();
            $totalBilled = (float)$billedRow['billed'];
            $avgInvoice  = $billedRow['cnt'] > 0 ? round($totalBilled / $billedRow['cnt'], 2) : 0;

            // Total clients (all time — not period-scoped intentionally)
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM clients WHERE user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            $totalClients = (int)$stmt->fetch()['cnt'];

            // Status counts in period
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
                WHERE user_id = ? AND deleted_at IS NULL $periodCond
            ");
            $stmt->execute([$userId]);
            $statusCounts = $stmt->fetch();

            // Monthly revenue in period
            $stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(issue_date, '%Y-%m') as month,
                    COALESCE(SUM(paid_amount), 0) as total,
                    COALESCE(SUM(total_amount), 0) as billed,
                    COUNT(*) as invoice_count
                FROM invoices 
                WHERE user_id = ? AND deleted_at IS NULL
                AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$userId, $monthsBack]);
            $monthlyRevenue = $stmt->fetchAll();

            // Recent invoices (last 5 — always)
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

            // Top clients by revenue in period
            $stmt = $db->prepare("
                SELECT 
                    c.id, c.name, c.company,
                    COALESCE(SUM(i.paid_amount), 0) as revenue,
                    COALESCE(SUM(i.total_amount), 0) as billed,
                    COUNT(i.id) as invoice_count
                FROM clients c
                LEFT JOIN invoices i ON c.id = i.client_id AND i.deleted_at IS NULL $periodCond
                WHERE c.user_id = ? AND c.deleted_at IS NULL
                GROUP BY c.id, c.name, c.company
                HAVING billed > 0
                ORDER BY revenue DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $topClients = $stmt->fetchAll();

            // Invoice breakdown for export (period-scoped, full detail)
            $stmt = $db->prepare("
                SELECT 
                    i.invoice_number, i.issue_date, i.due_date,
                    i.total_amount, i.paid_amount,
                    (i.total_amount - i.paid_amount) as balance_due,
                    i.status, i.currency,
                    c.name as client_name, c.company as client_company
                FROM invoices i
                JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ? AND i.deleted_at IS NULL $periodCond
                ORDER BY i.issue_date DESC
            ");
            $stmt->execute([$userId]);
            $invoiceBreakdown = $stmt->fetchAll();

            return [
                'success' => true,
                'data' => [
                    'period'          => $period,
                    'total_invoices'  => $totalInvoices,
                    'total_revenue'   => $totalRevenue,
                    'total_billed'    => $totalBilled,
                    'pending_amount'  => $pendingAmount,
                    'avg_invoice'     => $avgInvoice,
                    'total_clients'   => $totalClients,
                    'status_counts'   => [
                        'draft'   => (int)($statusCounts['draft']   ?? 0),
                        'sent'    => (int)($statusCounts['sent']    ?? 0),
                        'partial' => (int)($statusCounts['partial'] ?? 0),
                        'paid'    => (int)($statusCounts['paid']    ?? 0),
                        'overdue' => (int)($statusCounts['overdue'] ?? 0),
                    ],
                    'monthly_revenue'    => $monthlyRevenue,
                    'recent_invoices'    => $recentInvoices,
                    'top_clients'        => $topClients,
                    'invoice_breakdown'  => $invoiceBreakdown,
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
