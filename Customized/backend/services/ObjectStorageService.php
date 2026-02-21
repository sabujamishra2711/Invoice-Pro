<?php
// Object Storage Service - Handles cloud storage for PDFs and assets

class ObjectStorageService
{
    private $provider;
    private $bucket;
    private $region;
    private $accessKeyId;
    private $secretAccessKey;

    public function __construct()
    {
        $this->provider = $_ENV['OBJECT_STORAGE_PROVIDER'] ?? 'local'; // s3, gcs, local
        $this->bucket = $_ENV['OBJECT_STORAGE_BUCKET'] ?? 'invoices-storage';
        $this->region = $_ENV['OBJECT_STORAGE_REGION'] ?? 'us-east-1';
        $this->accessKeyId = $_ENV['OBJECT_STORAGE_KEY_ID'] ?? '';
        $this->secretAccessKey = $_ENV['OBJECT_STORAGE_SECRET'] ?? '';
    }

    /**
     * Upload a file to object storage
     * @param string $objectKey The key/path for the object in storage
     * @param string $filePath Local file path or binary content
     * @param string $contentType MIME type of the content
     * @return bool True if upload successful
     */
    public function upload($objectKey, $filePath, $contentType = 'application/pdf')
    {
        switch ($this->provider) {
            case 's3':
                return $this->uploadToS3($objectKey, $filePath, $contentType);
            case 'gcs':
                return $this->uploadToGCS($objectKey, $filePath, $contentType);
            case 'local':
            default:
                return $this->uploadToLocal($objectKey, $filePath);
        }
    }

    /**
     * Download a file from object storage
     * @param string $objectKey The key/path for the object in storage
     * @param string $destinationPath Local path to save the file
     * @return bool True if download successful
     */
    public function download($objectKey, $destinationPath)
    {
        switch ($this->provider) {
            case 's3':
                return $this->downloadFromS3($objectKey, $destinationPath);
            case 'gcs':
                return $this->downloadFromGCS($objectKey, $destinationPath);
            case 'local':
            default:
                return $this->downloadFromLocal($objectKey, $destinationPath);
        }
    }

    /**
     * Get a signed URL for temporary access to an object
     * @param string $objectKey The key/path for the object in storage
     * @param int $expirySeconds How long the URL should be valid (default 1 hour)
     * @return string|bool Signed URL or false on failure
     */
    public function getSignedUrl($objectKey, $expirySeconds = 3600)
    {
        switch ($this->provider) {
            case 's3':
                return $this->getS3SignedUrl($objectKey, $expirySeconds);
            case 'gcs':
                return $this->getGCSSignedUrl($objectKey, $expirySeconds);
            case 'local':
            default:
                // For local storage, return a temporary access URL
                return $this->getLocalSignedUrl($objectKey, $expirySeconds);
        }
    }

    /**
     * Check if an object exists in storage
     * @param string $objectKey The key/path for the object in storage
     * @return bool True if object exists
     */
    public function exists($objectKey)
    {
        switch ($this->provider) {
            case 's3':
                return $this->s3Exists($objectKey);
            case 'gcs':
                return $this->gcsExists($objectKey);
            case 'local':
            default:
                return $this->localExists($objectKey);
        }
    }

    /**
     * Delete an object from storage
     * @param string $objectKey The key/path for the object in storage
     * @return bool True if deletion successful
     */
    public function delete($objectKey)
    {
        switch ($this->provider) {
            case 's3':
                return $this->deleteFromS3($objectKey);
            case 'gcs':
                return $this->deleteFromGCS($objectKey);
            case 'local':
            default:
                return $this->deleteFromLocal($objectKey);
        }
    }

    // S3 Implementation
    private function uploadToS3($objectKey, $filePath, $contentType)
    {
        // This is a simplified implementation
        // In production, you'd use the AWS SDK for PHP
        // For now, we'll simulate with a local approach

        // In a real S3 implementation:
        /*
        $sdk = new Aws\Sdk([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ],
        ]);
        
        $s3 = $sdk->createS3();
        
        $result = $s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $objectKey,
            'Body' => fopen($filePath, 'r'),
            'ContentType' => $contentType,
        ]);
        
        return $result['@metadata']['statusCode'] === 200;
        */

        // For now, we'll use local storage as a placeholder
        return $this->uploadToLocal($objectKey, $filePath);
    }

    private function downloadFromS3($objectKey, $destinationPath)
    {
        // Placeholder implementation
        return $this->downloadFromLocal($objectKey, $destinationPath);
    }

    private function getS3SignedUrl($objectKey, $expirySeconds)
    {
        // Placeholder implementation
        return $this->getLocalSignedUrl($objectKey, $expirySeconds);
    }

    private function s3Exists($objectKey)
    {
        // Placeholder implementation
        return $this->localExists($objectKey);
    }

    private function deleteFromS3($objectKey)
    {
        // Placeholder implementation
        return $this->deleteFromLocal($objectKey);
    }

    // Google Cloud Storage Implementation
    private function uploadToGCS($objectKey, $filePath, $contentType)
    {
        // Placeholder implementation
        return $this->uploadToLocal($objectKey, $filePath);
    }

    private function downloadFromGCS($objectKey, $destinationPath)
    {
        // Placeholder implementation
        return $this->downloadFromLocal($objectKey, $destinationPath);
    }

    private function getGCSSignedUrl($objectKey, $expirySeconds)
    {
        // Placeholder implementation
        return $this->getLocalSignedUrl($objectKey, $expirySeconds);
    }

    private function gcsExists($objectKey)
    {
        // Placeholder implementation
        return $this->localExists($objectKey);
    }

    private function deleteFromGCS($objectKey)
    {
        // Placeholder implementation
        return $this->deleteFromLocal($objectKey);
    }

    // Local Storage Implementation (for development)
    private function uploadToLocal($objectKey, $filePath)
    {
        // For local development, we'll create a structured folder
        $basePath = $_ENV['STORAGE_PATH'] ?? __DIR__ . '/../../storage/object_storage/';

        // Create directory structure if it doesn't exist
        $fullPath = $basePath . $objectKey;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // If $filePath is a binary string, write it directly
        // If it's a file path, copy the file
        if (file_exists($filePath)) {
            return copy($filePath, $fullPath);
        } else {
            // Assume $filePath is the actual content to write
            return file_put_contents($fullPath, $filePath) !== false;
        }
    }

    private function downloadFromLocal($objectKey, $destinationPath)
    {
        $basePath = $_ENV['STORAGE_PATH'] ?? __DIR__ . '/../../storage/object_storage/';
        $sourcePath = $basePath . $objectKey;

        if (!file_exists($sourcePath)) {
            return false;
        }

        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return copy($sourcePath, $destinationPath);
    }

    private function getLocalSignedUrl($objectKey, $expirySeconds)
    {
        // In a real implementation, this would generate a time-limited signed URL
        // For local storage, we'll return a temporary access token

        // Create a temporary token
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + $expirySeconds;

        // Store the token and its metadata
        $tokenDir = __DIR__ . '/../../storage/tokens/';
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0755, true);
        }

        $tokenData = [
            'object_key' => $objectKey,
            'expires_at' => $expiresAt,
            'created_at' => time()
        ];

        file_put_contents($tokenDir . $token, json_encode($tokenData));

        // Return a URL that can be used to access the file via a download endpoint
        return API_URL . '/download/' . $token;
    }

    private function localExists($objectKey)
    {
        $basePath = $_ENV['STORAGE_PATH'] ?? __DIR__ . '/../../storage/object_storage/';
        $fullPath = $basePath . $objectKey;

        return file_exists($fullPath);
    }

    private function deleteFromLocal($objectKey)
    {
        $basePath = $_ENV['STORAGE_PATH'] ?? __DIR__ . '/../../storage/object_storage/';
        $fullPath = $basePath . $objectKey;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return true; // File doesn't exist, so deletion is "successful"
    }
}
