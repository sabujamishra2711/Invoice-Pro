<?php
// Session Manager - Handles Redis-based session storage for stateless operation

class SessionManager
{
    private static $redis = null;

    /**
     * Initialize Redis connection for session storage
     */
    public static function initRedis()
    {
        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            error_log("SessionManager: Redis extension not loaded, falling back to file-based sessions");
            ini_set('session.save_handler', 'files');
            return false;
        }

        if (self::$redis === null) {
            try {
                self::$redis = new Redis();

                // Connect to Redis (these values would typically come from config)
                $host = $_ENV['REDIS_HOST'] ?? 'localhost';
                $port = $_ENV['REDIS_PORT'] ?? 6379;
                $timeout = $_ENV['REDIS_TIMEOUT'] ?? 2.5;

                $connected = self::$redis->connect($host, $port, $timeout);

                if (!$connected) {
                    throw new Exception("Could not connect to Redis");
                }

                // Authenticate if password is provided
                $password = $_ENV['REDIS_PASSWORD'] ?? null;
                if ($password) {
                    self::$redis->auth($password);
                }

                // Select database
                $dbIndex = $_ENV['REDIS_DB_INDEX'] ?? 0;
                self::$redis->select($dbIndex);
            } catch (Exception $e) {
                error_log("SessionManager Redis connection error: " . $e->getMessage());
                // Fallback to file-based sessions if Redis is unavailable
                ini_set('session.save_handler', 'files');
                return false;
            }
        }

        return true;
    }

    /**
     * Configure PHP to use Redis for session storage
     */
    public static function configureSessionHandler()
    {
        if (!self::initRedis()) {
            return false;
        }

        // Configure session settings
        ini_set('session.save_handler', 'redis');
        ini_set('session.save_path', sprintf(
            "tcp://%s:%d",
            $_ENV['REDIS_HOST'] ?? 'localhost',
            $_ENV['REDIS_PORT'] ?? 6379
        ));

        // Session security settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');

        return true;
    }

    /**
     * Get session data by session ID
     */
    public static function getSession($sessionId)
    {
        if (!extension_loaded('redis') || !self::$redis) {
            self::initRedis();
        }

        if (!extension_loaded('redis') || !self::$redis) {
            return false;
        }

        $sessionData = self::$redis->get("PHPREDIS_SESSION:$sessionId");
        return $sessionData ? unserialize($sessionData) : [];
    }

    /**
     * Set session data by session ID
     */
    public static function setSession($sessionId, $data, $ttl = 1440) // 24 minutes default
    {
        if (!extension_loaded('redis') || !self::$redis) {
            self::initRedis();
        }

        if (!extension_loaded('redis') || !self::$redis) {
            return false;
        }

        return self::$redis->setex("PHPREDIS_SESSION:$sessionId", $ttl, serialize($data));
    }

    /**
     * Destroy session by session ID
     */
    public static function destroySession($sessionId)
    {
        if (!extension_loaded('redis') || !self::$redis) {
            self::initRedis();
        }

        if (!extension_loaded('redis') || !self::$redis) {
            return false;
        }

        return self::$redis->del("PHPREDIS_SESSION:$sessionId") > 0;
    }

    /**
     * Clean up expired sessions
     */
    public static function gcSessions($maxlifetime)
    {
        // Redis handles expiration automatically, so this is a no-op
        return true;
    }
}
