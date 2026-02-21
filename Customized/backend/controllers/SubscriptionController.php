<?php
require_once __DIR__ . '/../helpers/TierGuard.php';

/**
 * SubscriptionController — provides subscription status to the frontend
 * and handles the version.limits endpoint.
 */
class SubscriptionController
{
    // GET subscription.status  — called on every app load to check subscription state
    public function getStatus($input)
    {
        $userId = authenticateRequest();
        if (!$userId) return $this->unauth();

        $info   = TierGuard::getLimitsInfo($userId);
        $status = $info['status'];

        // Compute days until free period ends (for countdown banner)
        $freeEndTs  = strtotime(DEPLOYMENT_DATE . ' +' . FREE_PERIOD_MONTHS . ' months');
        $daysToFree = max(0, (int)ceil(($freeEndTs - time()) / 86400));

        return [
            'success' => true,
            'data'    => array_merge($info, [
                'app_title'         => APP_TITLE,
                'client_name'       => CLIENT_NAME,
                'primary_color'     => PRIMARY_COLOR,
                'renewal_fee_inr'   => RENEWAL_FEE_INR,
                'renewal_fee_paise' => RENEWAL_FEE_PAISE,
                'free_period_months'=> FREE_PERIOD_MONTHS,
                'deployment_date'   => DEPLOYMENT_DATE,
                'days_to_free_end'  => $daysToFree,
                'is_readonly'       => ($status === 'readonly'),
                'is_grace'          => ($status === 'grace'),
                'is_active'         => ($status === 'active'),
            ]),
        ];
    }

    // GET version.limits  — kept for backwards compat with existing frontend calls
    public function getLimits($input)
    {
        return $this->getStatus($input);
    }

    // POST version.plan.set  — admin override: manually activate business plan
    // Use this to pre-activate a client's first month without requiring payment
    public function adminActivate($input)
    {
        $userId = authenticateRequest();
        if (!$userId) return $this->unauth();

        $months = max(1, (int)($input['months'] ?? 1));
        $expiry = date('Y-m-d H:i:s', strtotime("+{$months} months"));

        $db = getDB();
        $db->prepare("
            INSERT INTO plan_subscriptions (user_id, plan, max_clients, max_invoices, expires_at)
            VALUES (:uid, 'business', -1, -1, :exp)
            ON DUPLICATE KEY UPDATE
                plan         = 'business',
                max_clients  = -1,
                max_invoices = -1,
                expires_at   = :exp2,
                activated_at = CURRENT_TIMESTAMP
        ")->execute([':uid' => $userId, ':exp' => $expiry, ':exp2' => $expiry]);

        $db->prepare("UPDATE users SET plan = 'business' WHERE id = :uid")->execute([':uid' => $userId]);

        return [
            'success' => true,
            'message' => "Business plan activated for {$months} month(s). Expires: {$expiry}",
            'data'    => TierGuard::getLimitsInfo($userId),
        ];
    }

    private function unauth(): array
    {
        return ['success' => false, 'error_code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.', 'http_code' => 401];
    }
}
