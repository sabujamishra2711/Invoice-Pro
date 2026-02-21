<?php
// Rate Limiting Service

class RateLimiter
{
    private $db;
    private $maxRequests;
    private $timeWindow; // in seconds

    public function __construct($maxRequests = 60, $timeWindow = 3600)
    { // 60 requests per hour by default
        $this->db = getDB();
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
    }

    // Check if user can make a request
    public function canRequest($userId, $endpoint = 'general')
    {
        $currentTime = time();
        $windowStart = $currentTime - $this->timeWindow;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as request_count
            FROM rate_limits
            WHERE user_id = ? AND endpoint = ? AND request_time > ?
        ");
        $stmt->execute([$userId, $endpoint, date('Y-m-d H:i:s', $windowStart)]);
        $result = $stmt->fetch();

        return $result['request_count'] < $this->maxRequests;
    }

    // Record a request
    public function recordRequest($userId, $endpoint = 'general')
    {
        $stmt = $this->db->prepare("
            INSERT INTO rate_limits (user_id, endpoint, request_time)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $endpoint]);
    }

    // Check if IP can make a request
    public function canRequestByIP($ip, $endpoint = 'general', $maxRequests = 100, $timeWindow = 3600)
    {
        $currentTime = time();
        $windowStart = $currentTime - $timeWindow;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as request_count
            FROM rate_limits_ip
            WHERE ip_address = ? AND endpoint = ? AND request_time > ?
        ");
        $stmt->execute([$ip, $endpoint, date('Y-m-d H:i:s', $windowStart)]);
        $result = $stmt->fetch();

        return $result['request_count'] < $maxRequests;
    }

    // Record a request by IP
    public function recordRequestByIP($ip, $endpoint = 'general')
    {
        $stmt = $this->db->prepare("
            INSERT INTO rate_limits_ip (ip_address, endpoint, request_time)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip, $endpoint]);
    }

    // Check login attempts
    public function canLogin($identifier, $maxAttempts = 5, $lockoutTime = 900)
    { // 5 attempts, 15 min lockout
        $currentTime = time();
        $lockoutStart = $currentTime - $lockoutTime;

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempt_count
            FROM login_attempts
            WHERE identifier = ? AND attempt_time > ? AND success = 0
        ");
        $stmt->execute([$identifier, date('Y-m-d H:i:s', $lockoutStart)]);
        $result = $stmt->fetch();

        return $result['attempt_count'] < $maxAttempts;
    }

    // Record login attempt
    public function recordLoginAttempt($identifier, $success = false, $userId = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (identifier, user_id, success, attempt_time)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$identifier, $userId, $success ? 1 : 0]);
    }

    // Global rate limit check
    public function isRateLimited($userId = null, $ip = null, $endpoint = 'general')
    {
        $checks = [];

        // Check user rate limit if user ID provided
        if ($userId && !$this->canRequest($userId, $endpoint)) {
            return true;
        }

        // Check IP rate limit
        if ($ip && !$this->canRequestByIP($ip, $endpoint)) {
            return true;
        }

        return false;
    }

    // Apply rate limiting to a request
    public function applyRateLimit($userId = null, $ip = null, $endpoint = 'general')
    {
        if ($this->isRateLimited($userId, $ip, $endpoint)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Rate limit exceeded. Please try again later.'
                ]
            ]);
            exit();
        }

        // Record the request
        if ($userId) {
            $this->recordRequest($userId, $endpoint);
        }
        if ($ip) {
            $this->recordRequestByIP($ip, $endpoint);
        }
    }
}
