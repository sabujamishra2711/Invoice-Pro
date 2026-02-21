<?php
// TierGuard — enforces per-user plan limits
require_once __DIR__ . '/../version_config.php';
require_once __DIR__ . '/../config.php';

class TierGuard
{
    /**
     * Look up the plan for a user from the DB.
     */
    public static function getUserPlan(int $userId): string
    {
        $db = getDB();
        $stmt = $db->prepare("SELECT plan FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row['plan'] ?? VersionConfig::PLAN_PRO;
    }

    /**
     * Get current counts (clients + invoices) for a user.
     */
    public static function getUsage(int $userId): array
    {
        $db = getDB();

        $stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        $clients = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        $invoices = (int)$stmt->fetchColumn();

        return ['clients' => $clients, 'invoices' => $invoices];
    }

    /**
     * Return full limit info for the user: plan, usage, limits, percentages.
     */
    public static function getLimitsInfo(int $userId): array
    {
        $plan    = self::getUserPlan($userId);
        $usage   = self::getUsage($userId);
        $features = VersionConfig::getAllFeatures($plan);

        $clientLimit  = VersionConfig::getLimit('max_clients', $plan);
        $invoiceLimit = VersionConfig::getLimit('max_invoices', $plan);

        return [
            'plan'          => $plan,
            'plan_label'    => VersionConfig::$planLabels[$plan] ?? ucfirst($plan),
            'usage'         => $usage,
            'limits'        => [
                'max_clients'  => $clientLimit,
                'max_invoices' => $invoiceLimit,
            ],
            'pct'           => [
                'clients'  => $clientLimit > 0  ? round(($usage['clients']  / $clientLimit)  * 100) : 0,
                'invoices' => $invoiceLimit > 0 ? round(($usage['invoices'] / $invoiceLimit) * 100) : 0,
            ],
            'features'      => $features,
            'plans'         => VersionConfig::getAllPlans(),
        ];
    }

    /**
     * Assert that the user can create one more client.
     * Throws a LimitException on failure.
     */
    public static function assertCanCreateClient(int $userId): void
    {
        $plan  = self::getUserPlan($userId);
        $usage = self::getUsage($userId);

        if (!VersionConfig::checkLimit('max_clients', $usage['clients'], $plan)) {
            $limit = VersionConfig::getLimit('max_clients', $plan);
            throw new LimitException(
                "client",
                $usage['clients'],
                $limit,
                $plan,
                "You've reached the {$limit}-client limit on the " . VersionConfig::$planLabels[$plan] . " plan. Upgrade to add more clients."
            );
        }
    }

    /**
     * Assert that the user can create one more invoice.
     */
    public static function assertCanCreateInvoice(int $userId): void
    {
        $plan  = self::getUserPlan($userId);
        $usage = self::getUsage($userId);

        if (!VersionConfig::checkLimit('max_invoices', $usage['invoices'], $plan)) {
            $limit = VersionConfig::getLimit('max_invoices', $plan);
            throw new LimitException(
                "invoice",
                $usage['invoices'],
                $limit,
                $plan,
                "You've reached the {$limit}-invoice limit on the " . VersionConfig::$planLabels[$plan] . " plan. Upgrade to create more invoices."
            );
        }
    }

    /**
     * Assert a boolean feature is available.
     */
    public static function assertFeature(string $feature, int $userId): void
    {
        $plan = self::getUserPlan($userId);
        if (!VersionConfig::isFeatureAvailable($feature, $plan)) {
            $planLabel = VersionConfig::$planLabels[$plan] ?? ucfirst($plan);
            throw new LimitException(
                $feature,
                0,
                0,
                $plan,
                "The '{$feature}' feature is not available on the {$planLabel} plan. Please upgrade."
            );
        }
    }
}

/**
 * Structured exception for limit/tier violations.
 */
class LimitException extends RuntimeException
{
    public string $resource;
    public int    $current;
    public int    $limit;
    public string $plan;

    public function __construct(string $resource, int $current, int $limit, string $plan, string $message)
    {
        parent::__construct($message);
        $this->resource = $resource;
        $this->current  = $current;
        $this->limit    = $limit;
        $this->plan     = $plan;
    }
}
