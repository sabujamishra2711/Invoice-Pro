<?php
// Version/Plan Configuration — per-user tier limits

class VersionConfig
{
    const PLAN_PRO          = 'pro';
    const PLAN_PROFESSIONAL = 'professional';
    const PLAN_ENTERPRISE   = 'enterprise';

    // Human-readable labels
    public static $planLabels = [
        'pro'          => 'Pro',
        'professional' => 'Professional',
        'enterprise'   => 'Enterprise',
    ];

    // All plan definitions in one place
    private static $plans = [
        'pro' => [
            'max_clients'          => 10,
            'max_invoices'         => 25,
            'basic_reports'        => true,
            'export_reports'       => false,
            'email_invoices'       => true,
            'custom_branding'      => false,
            'multi_currency'       => false,
            'recurring_invoices'   => false,
            'api_access'           => false,
            'team_collaboration'   => false,
            'advanced_reports'     => false,
            'bulk_import_export'   => false,
            'priority_support'     => false,
        ],
        'professional' => [
            'max_clients'          => 100,
            'max_invoices'         => 500,
            'basic_reports'        => true,
            'export_reports'       => true,
            'email_invoices'       => true,
            'custom_branding'      => true,
            'multi_currency'       => true,
            'recurring_invoices'   => true,
            'api_access'           => true,
            'team_collaboration'   => false,
            'advanced_reports'     => true,
            'bulk_import_export'   => true,
            'priority_support'     => true,
        ],
        'enterprise' => [
            'max_clients'          => -1,   // unlimited
            'max_invoices'         => -1,   // unlimited
            'basic_reports'        => true,
            'export_reports'       => true,
            'email_invoices'       => true,
            'custom_branding'      => true,
            'multi_currency'       => true,
            'recurring_invoices'   => true,
            'api_access'           => true,
            'team_collaboration'   => true,
            'advanced_reports'     => true,
            'bulk_import_export'   => true,
            'priority_support'     => true,
        ],
    ];

    /**
     * Get all features for a given plan name.
     */
    public static function getAllFeatures(string $plan): array
    {
        return self::$plans[$plan] ?? self::$plans[self::PLAN_PRO];
    }

    /**
     * Get a single feature value for a plan.
     */
    public static function getFeature(string $feature, string $plan): mixed
    {
        $features = self::getAllFeatures($plan);
        return $features[$feature] ?? null;
    }

    /**
     * Is a boolean feature available on this plan?
     */
    public static function isFeatureAvailable(string $feature, string $plan): bool
    {
        $val = self::getFeature($feature, $plan);
        if ($val === null) return false;
        if (is_numeric($val)) return ((int)$val) !== 0; // -1 = unlimited = true, 0 = disabled
        return (bool)$val;
    }

    /**
     * Check whether $current is within the plan's limit for $feature.
     * Returns true if unlimited (-1) or current <= limit.
     */
    public static function checkLimit(string $feature, int $current, string $plan): bool
    {
        $limit = self::getFeature($feature, $plan);
        if ($limit === null)  return false;
        if ((int)$limit === -1) return true; // unlimited
        return $current < (int)$limit;       // strictly less-than means "can add one more"
    }

    /**
     * Get the numeric limit (or -1 for unlimited).
     */
    public static function getLimit(string $feature, string $plan): int
    {
        $val = self::getFeature($feature, $plan);
        return $val === null ? 0 : (int)$val;
    }

    /**
     * Return all plan definitions (for the upgrade modal).
     */
    public static function getAllPlans(): array
    {
        return self::$plans;
    }
}
