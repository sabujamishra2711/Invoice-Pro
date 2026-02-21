<?php
// Version Information Controller
require_once __DIR__ . '/../version_config.php';
require_once __DIR__ . '/../helpers/VersionCheck.php';

class VersionController
{
    public function getVersionInfo($input)
    {
        try {
            $versionInfo = VersionCheck::getVersionInfo();

            return [
                'success' => true,
                'data' => $versionInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'VERSION_INFO_FAILED',
                'message' => 'Failed to fetch version information',
                'http_code' => 500
            ];
        }
    }

    public function getFeatureAvailability($input)
    {
        try {
            $featureAvailability = VersionCheck::getFeatureAvailability();

            return [
                'success' => true,
                'data' => [
                    'features' => $featureAvailability,
                    'version' => VersionConfig::getCurrentVersion()
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_code' => 'FEATURE_AVAILABILITY_FAILED',
                'message' => 'Failed to fetch feature availability',
                'http_code' => 500
            ];
        }
    }
}
