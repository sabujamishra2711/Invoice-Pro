<?php
// Version Check Helper for Feature Limit Enforcement
require_once __DIR__ . '/../version_config.php';

class VersionCheck
{
    /**
     * Check if a feature is available in the current version
     */
    public static function isFeatureAvailable($feature)
    {
        return VersionConfig::isFeatureAvailable($feature);
    }

    /**
     * Check if a limit is exceeded
     */
    public static function checkLimit($limitFeature, $currentValue)
    {
        return VersionConfig::checkLimit($limitFeature, $currentValue);
    }

    /**
     * Enforce a feature limitation
     * @throws Exception if feature is not available or limit is exceeded
     */
    public static function enforceFeature($feature, $currentValue = null)
    {
        if ($feature === 'max_clients' || $feature === 'max_invoices') {
            // For limit features, check if the limit is exceeded
            if (!self::checkLimit($feature, $currentValue)) {
                $limit = VersionConfig::getFeature($feature);
                $version = VersionConfig::getCurrentVersion();

                if ($limit === -1) {
                    return true; // Unlimited
                }

                throw new Exception(
                    "You've reached the limit for {$feature}. Upgrade to a higher version to increase this limit. Current limit: {$limit} in {$version} version."
                );
            }
        } else {
            // For boolean features, check if available
            if (!self::isFeatureAvailable($feature)) {
                $version = VersionConfig::getCurrentVersion();
                throw new Exception(
                    "Feature '{$feature}' is not available in {$version} version. Please upgrade to access this feature."
                );
            }
        }
    }

    /**
     * Get current version information
     */
    public static function getVersionInfo()
    {
        $version = VersionConfig::getCurrentVersion();
        $features = VersionConfig::getAllFeatures();

        return [
            'version' => $version,
            'features' => $features
        ];
    }

    /**
     * Get feature availability for the current version
     */
    public static function getFeatureAvailability()
    {
        $version = VersionConfig::getCurrentVersion();
        $features = VersionConfig::getAllFeatures($version);

        $availability = [];
        foreach ($features as $feature => $value) {
            $availability[$feature] = [
                'available' => self::isFeatureAvailable($feature),
                'value' => $value
            ];
        }

        return $availability;
    }
}
