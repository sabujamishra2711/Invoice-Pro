<?php
/**
 * Plan definitions – source of truth for features & default limits.
 * Per-user overrides (enterprise custom limits) live in plan_subscriptions.
 */
return [
    'pro' => [
        'label'          => 'Pro',
        'max_clients'    => 10,
        'max_invoices'   => 20,
        'features' => [
            'recurring_invoices' => false,
            'custom_branding'    => false,
            'export_reports'     => false,
            'api_access'         => false,
            'priority_support'   => false,
            'multi_currency'     => false,
        ],
    ],
    'professional' => [
        'label'          => 'Professional',
        'max_clients'    => 50,
        'max_invoices'   => 100,
        'features' => [
            'recurring_invoices' => true,
            'custom_branding'    => true,
            'export_reports'     => true,
            'api_access'         => false,
            'priority_support'   => false,
            'multi_currency'     => true,
        ],
    ],
    'enterprise' => [
        'label'          => 'Enterprise',
        'max_clients'    => -1,   // overridden per-user from plan_subscriptions
        'max_invoices'   => -1,
        'features' => [
            'recurring_invoices' => true,
            'custom_branding'    => true,
            'export_reports'     => true,
            'api_access'         => true,
            'priority_support'   => true,
            'multi_currency'     => true,
        ],
    ],
];
