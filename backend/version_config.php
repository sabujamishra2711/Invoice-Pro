<?php
// Version Configuration for Invoice Management System
// Defines features available in each edition

class VersionConfig
{
    const VERSION_PRO = 'pro';
    const VERSION_PROFESSIONAL = 'professional';
    const VERSION_ENTERPRISE = 'enterprise';

    private static $features = null;

    private static function getFeatures()
    {
        if (self::$features === null) {
            self::$features = [
                self::VERSION_PRO => [
                    'max_clients' => $_ENV['MAX_CLIENTS'] ?? 50,
                    'max_invoices' => $_ENV['MAX_INVOICES'] ?? 100,
                    'basic_reports' => true,
                    'custom_fields' => $_ENV['CUSTOM_FIELDS_ENABLED'] ?? false,
                    'multi_currency' => $_ENV['MULTI_CURRENCY_ENABLED'] ?? false,
                    'inventory_tracking' => $_ENV['INVENTORY_TRACKING_ENABLED'] ?? false,
                    'advanced_tax_calculations' => $_ENV['ADVANCED_TAX_CALCULATIONS_ENABLED'] ?? false,
                    'bulk_import_export' => $_ENV['BULK_IMPORT_EXPORT_ENABLED'] ?? false,
                    'email_automation' => $_ENV['EMAIL_AUTOMATION_ENABLED'] ?? false,
                    'api_access' => $_ENV['API_ACCESS_ENABLED'] ?? false,
                    'team_collaboration' => $_ENV['TEAM_COLLABORATION_ENABLED'] ?? false,
                    'custom_branding' => $_ENV['CUSTOM_BRANDING_ENABLED'] ?? false,
                    'advanced_security' => $_ENV['ADVANCED_SECURITY_ENABLED'] ?? false,
                    'priority_support' => $_ENV['PRIORITY_SUPPORT_ENABLED'] ?? false,
                    'backup_restore' => $_ENV['BACKUP_RESTORE_ENABLED'] ?? false,
                    'audit_trail' => $_ENV['AUDIT_TRAIL_ENABLED'] ?? false,
                    'recurring_invoices' => $_ENV['RECURRING_INVOICES_ENABLED'] ?? false,
                    'expense_tracking' => $_ENV['EXPENSE_TRACKING_ENABLED'] ?? false,
                    'project_tracking' => $_ENV['PROJECT_TRACKING_ENABLED'] ?? false,
                    'multi_user' => $_ENV['MULTI_USER_ENABLED'] ?? false,
                    'custom_templates' => $_ENV['CUSTOM_TEMPLATES_ENABLED'] ?? false,
                    'automated_reminders' => $_ENV['AUTOMATED_REMINDERS_ENABLED'] ?? false,
                    'financial_reporting' => $_ENV['FINANCIAL_REPORTING_ENABLED'] ?? false,
                    'data_retention' => $_ENV['DATA_RETENTION_DAYS'] ?? 30, // days
                    'storage_limit' => $_ENV['STORAGE_LIMIT'] ?? 100, // MB
                    'support_tickets_per_month' => $_ENV['SUPPORT_TICKETS_PER_MONTH'] ?? 2,
                ],
                self::VERSION_PROFESSIONAL => [
                    'max_clients' => $_ENV['MAX_CLIENTS'] ?? 200,
                    'max_invoices' => $_ENV['MAX_INVOICES'] ?? 500,
                    'basic_reports' => true,
                    'custom_fields' => $_ENV['CUSTOM_FIELDS_ENABLED'] ?? true,
                    'multi_currency' => $_ENV['MULTI_CURRENCY_ENABLED'] ?? true,
                    'inventory_tracking' => $_ENV['INVENTORY_TRACKING_ENABLED'] ?? true,
                    'advanced_tax_calculations' => $_ENV['ADVANCED_TAX_CALCULATIONS_ENABLED'] ?? true,
                    'bulk_import_export' => $_ENV['BULK_IMPORT_EXPORT_ENABLED'] ?? true,
                    'email_automation' => $_ENV['EMAIL_AUTOMATION_ENABLED'] ?? true,
                    'api_access' => $_ENV['API_ACCESS_ENABLED'] ?? true,
                    'team_collaboration' => $_ENV['TEAM_COLLABORATION_ENABLED'] ?? false,
                    'custom_branding' => $_ENV['CUSTOM_BRANDING_ENABLED'] ?? true,
                    'advanced_security' => $_ENV['ADVANCED_SECURITY_ENABLED'] ?? false,
                    'priority_support' => $_ENV['PRIORITY_SUPPORT_ENABLED'] ?? true,
                    'backup_restore' => $_ENV['BACKUP_RESTORE_ENABLED'] ?? true,
                    'audit_trail' => $_ENV['AUDIT_TRAIL_ENABLED'] ?? true,
                    'recurring_invoices' => $_ENV['RECURRING_INVOICES_ENABLED'] ?? true,
                    'expense_tracking' => $_ENV['EXPENSE_TRACKING_ENABLED'] ?? true,
                    'project_tracking' => $_ENV['PROJECT_TRACKING_ENABLED'] ?? false,
                    'multi_user' => $_ENV['MULTI_USER_ENABLED'] ?? true,
                    'custom_templates' => $_ENV['CUSTOM_TEMPLATES_ENABLED'] ?? true,
                    'automated_reminders' => $_ENV['AUTOMATED_REMINDERS_ENABLED'] ?? true,
                    'financial_reporting' => $_ENV['FINANCIAL_REPORTING_ENABLED'] ?? true,
                    'data_retention' => $_ENV['DATA_RETENTION_DAYS'] ?? 365, // days
                    'storage_limit' => $_ENV['STORAGE_LIMIT'] ?? 1024, // MB
                    'support_tickets_per_month' => $_ENV['SUPPORT_TICKETS_PER_MONTH'] ?? 10,
                ],
                self::VERSION_ENTERPRISE => [
                    'max_clients' => $_ENV['MAX_CLIENTS'] ?? -1, // unlimited
                    'max_invoices' => $_ENV['MAX_INVOICES'] ?? -1, // unlimited
                    'basic_reports' => true,
                    'custom_fields' => $_ENV['CUSTOM_FIELDS_ENABLED'] ?? true,
                    'multi_currency' => $_ENV['MULTI_CURRENCY_ENABLED'] ?? true,
                    'inventory_tracking' => $_ENV['INVENTORY_TRACKING_ENABLED'] ?? true,
                    'advanced_tax_calculations' => $_ENV['ADVANCED_TAX_CALCULATIONS_ENABLED'] ?? true,
                    'bulk_import_export' => $_ENV['BULK_IMPORT_EXPORT_ENABLED'] ?? true,
                    'email_automation' => $_ENV['EMAIL_AUTOMATION_ENABLED'] ?? true,
                    'api_access' => $_ENV['API_ACCESS_ENABLED'] ?? true,
                    'team_collaboration' => $_ENV['TEAM_COLLABORATION_ENABLED'] ?? true,
                    'custom_branding' => $_ENV['CUSTOM_BRANDING_ENABLED'] ?? true,
                    'advanced_security' => $_ENV['ADVANCED_SECURITY_ENABLED'] ?? true,
                    'priority_support' => $_ENV['PRIORITY_SUPPORT_ENABLED'] ?? true,
                    'backup_restore' => $_ENV['BACKUP_RESTORE_ENABLED'] ?? true,
                    'audit_trail' => $_ENV['AUDIT_TRAIL_ENABLED'] ?? true,
                    'recurring_invoices' => $_ENV['RECURRING_INVOICES_ENABLED'] ?? true,
                    'expense_tracking' => $_ENV['EXPENSE_TRACKING_ENABLED'] ?? true,
                    'project_tracking' => $_ENV['PROJECT_TRACKING_ENABLED'] ?? true,
                    'multi_user' => $_ENV['MULTI_USER_ENABLED'] ?? true,
                    'custom_templates' => $_ENV['CUSTOM_TEMPLATES_ENABLED'] ?? true,
                    'automated_reminders' => $_ENV['AUTOMATED_REMINDERS_ENABLED'] ?? true,
                    'financial_reporting' => $_ENV['FINANCIAL_REPORTING_ENABLED'] ?? true,
                    'data_retention' => $_ENV['DATA_RETENTION_DAYS'] ?? -1, // unlimited
                    'storage_limit' => $_ENV['STORAGE_LIMIT'] ?? -1, // unlimited
                    'support_tickets_per_month' => $_ENV['SUPPORT_TICKETS_PER_MONTH'] ?? -1, // unlimited
                ]
            ];
        }

        return self::$features;
    }

    public static function getFeature($feature, $version = null)
    {
        if ($version === null) {
            $version = self::getCurrentVersion();
        }

        $features = self::getFeatures();
        
        if (isset($features[$version][$feature])) {
            return $features[$version][$feature];
        }

        return null;
    }

    public static function getAllFeatures($version = null)
    {
        if ($version === null) {
            $version = self::getCurrentVersion();
        }

        $features = self::getFeatures();
        
        if (isset($features[$version])) {
            return $features[$version];
        }

        return [];
    }

    public static function getCurrentVersion()
    {
        // Default to Pro version if not specified
        return $_ENV['APP_VERSION'] ?? self::VERSION_PRO;
    }

    public static function isFeatureAvailable($feature, $version = null)
    {
        $value = self::getFeature($feature, $version);

        if ($value === null) {
            return false;
        }

        // For numeric limits (-1 means unlimited)
        if (is_numeric($value)) {
            return $value != 0; // 0 means disabled, -1 or positive means enabled
        }

        return (bool)$value;
    }

    public static function checkLimit($limit_feature, $current_value, $version = null)
    {
        $limit = self::getFeature($limit_feature, $version);

        if ($limit === -1) { // Unlimited
            return true;
        }

        if ($limit === null) {
            return false; // Feature doesn't exist
        }

        return $current_value <= $limit;
    }
}
