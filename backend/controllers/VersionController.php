<?php
require_once __DIR__ . '/../helpers/TierGuard.php';

class VersionController
{
    /**
     * GET version.limits — returns plan, usage, limits, feature flags for the current user.
     */
    public function getLimits($input)
    {
        try {
            $userId = authenticateRequest();
            $info   = TierGuard::getLimitsInfo($userId);

            return [
                'success' => true,
                'data'    => $info
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'error_code' => 'LIMITS_FETCH_FAILED',
                'message'    => 'Failed to fetch plan limits',
                'http_code'  => 500
            ];
        }
    }

    /**
     * POST version.plan.set — (admin/dev) change the current user's plan.
     * Body: { "plan": "professional" }
     */
    public function setPlan($input)
    {
        try {
            $userId = authenticateRequest();
            $plan   = $input['plan'] ?? null;

            $allowed = [VersionConfig::PLAN_PRO, VersionConfig::PLAN_PROFESSIONAL, VersionConfig::PLAN_ENTERPRISE];
            if (!in_array($plan, $allowed)) {
                return [
                    'success'    => false,
                    'error_code' => 'INVALID_PLAN',
                    'message'    => 'Invalid plan. Must be one of: ' . implode(', ', $allowed),
                    'http_code'  => 400
                ];
            }

            $db = getDB();
            $db->prepare("UPDATE users SET plan = ? WHERE id = ?")->execute([$plan, $userId]);

            $info = TierGuard::getLimitsInfo($userId);
            return [
                'success' => true,
                'message' => 'Plan updated to ' . VersionConfig::$planLabels[$plan],
                'data'    => $info
            ];
        } catch (Exception $e) {
            return [
                'success'    => false,
                'error_code' => 'PLAN_UPDATE_FAILED',
                'message'    => 'Failed to update plan',
                'http_code'  => 500
            ];
        }
    }
}
