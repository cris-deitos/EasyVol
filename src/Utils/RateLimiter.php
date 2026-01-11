<?php
namespace EasyVol\Utils;

/**
 * Rate Limiter Utility
 * 
 * Prevents brute force attacks by limiting the number of attempts
 * from a specific IP address or username within a time window
 */
class RateLimiter {
    private $db;
    
    // Default configuration
    const DEFAULT_MAX_ATTEMPTS = 5;
    const DEFAULT_WINDOW_MINUTES = 15;
    const DEFAULT_LOCKOUT_MINUTES = 15;
    
    /**
     * Constructor
     * 
     * @param \EasyVol\Database $db Database instance
     */
    public function __construct(\EasyVol\Database $db) {
        $this->db = $db;
    }
    
    /**
     * Check if an identifier (IP or username) is rate limited
     * 
     * @param string $identifier The identifier to check (IP address or username)
     * @param string $action The action being rate limited (e.g., 'login', 'reset_password')
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $windowMinutes Time window in minutes
     * @return array ['allowed' => bool, 'attempts' => int, 'reset_at' => string|null]
     */
    public function check($identifier, $action = 'login', $maxAttempts = self::DEFAULT_MAX_ATTEMPTS, $windowMinutes = self::DEFAULT_WINDOW_MINUTES) {
        // Calculate the window start time
        $windowStart = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        
        // Count attempts within the window
        $sql = "SELECT COUNT(*) as attempt_count, 
                       MAX(attempted_at) as last_attempt
                FROM rate_limit_attempts 
                WHERE identifier = ? 
                  AND action = ? 
                  AND attempted_at >= ?";
        
        try {
            $result = $this->db->fetchOne($sql, [$identifier, $action, $windowStart]);
            
            if (!$result) {
                return [
                    'allowed' => true,
                    'attempts' => 0,
                    'reset_at' => null
                ];
            }
            
            $attemptCount = (int)$result['attempt_count'];
            $allowed = $attemptCount < $maxAttempts;
            
            // Calculate when the rate limit will reset
            $resetAt = null;
            if (!$allowed && $result['last_attempt']) {
                $resetAt = date('Y-m-d H:i:s', strtotime($result['last_attempt'] . " +{$windowMinutes} minutes"));
            }
            
            return [
                'allowed' => $allowed,
                'attempts' => $attemptCount,
                'reset_at' => $resetAt
            ];
            
        } catch (\Exception $e) {
            // If rate_limit_attempts table doesn't exist yet, allow the attempt
            // This ensures backwards compatibility
            error_log("Rate limiter check failed: " . $e->getMessage());
            return [
                'allowed' => true,
                'attempts' => 0,
                'reset_at' => null
            ];
        }
    }
    
    /**
     * Record an attempt for rate limiting
     * 
     * @param string $identifier The identifier (IP address or username)
     * @param string $action The action being attempted
     * @param bool $success Whether the attempt was successful
     * @return bool True if recorded successfully
     */
    public function recordAttempt($identifier, $action = 'login', $success = false) {
        try {
            $sql = "INSERT INTO rate_limit_attempts (identifier, action, success, attempted_at) 
                    VALUES (?, ?, ?, NOW())";
            
            $this->db->execute($sql, [$identifier, $action, $success ? 1 : 0]);
            return true;
            
        } catch (\Exception $e) {
            // If table doesn't exist, silently fail
            error_log("Rate limiter record failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset attempts for an identifier (useful after successful login)
     * 
     * @param string $identifier The identifier to reset
     * @param string $action The action to reset
     * @return bool True if reset successfully
     */
    public function reset($identifier, $action = 'login') {
        try {
            $sql = "DELETE FROM rate_limit_attempts 
                    WHERE identifier = ? AND action = ?";
            
            $this->db->execute($sql, [$identifier, $action]);
            return true;
            
        } catch (\Exception $e) {
            error_log("Rate limiter reset failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old attempts (maintenance task)
     * Removes attempts older than the specified days
     * 
     * @param int $days Number of days to keep
     * @return int Number of records deleted
     */
    public function cleanup($days = 30) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $sql = "DELETE FROM rate_limit_attempts WHERE attempted_at < ?";
            $stmt = $this->db->execute($sql, [$cutoffDate]);
            
            return $stmt->rowCount();
            
        } catch (\Exception $e) {
            error_log("Rate limiter cleanup failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get current client IP address
     * Handles proxy scenarios with X-Forwarded-For header
     * 
     * SECURITY WARNING: X-Forwarded-For header can be spoofed by attackers!
     * 
     * For production environments:
     * 1. Configure web server (nginx/Apache) to only set X-Forwarded-For from trusted IPs
     * 2. Or use getTrustedClientIp() with a whitelist of proxy IPs
     * 3. Or simply rely on REMOTE_ADDR if not behind a proxy
     * 
     * Example nginx config:
     * ```
     * set_real_ip_from 10.0.0.0/8;  # Internal network
     * real_ip_header X-Forwarded-For;
     * ```
     * 
     * @return string IP address
     */
    public static function getClientIp() {
        // Check for proxy headers
        // WARNING: These can be spoofed! Use only behind trusted proxies.
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs, take the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            // Most reliable - direct connection IP (cannot be spoofed)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        // Validate IP address format
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        // Fallback to safe default if invalid
        return '0.0.0.0';
    }
    
    /**
     * Get client IP from trusted proxies only
     * Use this method if you have a whitelist of trusted proxy IPs
     * 
     * @param array $trustedProxies Array of trusted proxy IP addresses
     * @return string IP address
     */
    public static function getTrustedClientIp(array $trustedProxies = []) {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // If not from a trusted proxy, use REMOTE_ADDR directly
        if (empty($trustedProxies) || !in_array($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }
        
        // From trusted proxy - check X-Forwarded-For
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        return $remoteAddr;
    }
}
