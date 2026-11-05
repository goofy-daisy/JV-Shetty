<?php
/**
 * Rate Limiting — IP + key based, backed by rate_limit_attempts DB table.
 * Usage: RateLimit::check('login', 10, 60) — returns false if over limit.
 */

class RateLimit {

    /**
     * Check if the current IP is allowed to proceed.
     *
     * @param string $key        Unique identifier for this action (e.g. 'login', 'password_reset')
     * @param int    $max        Maximum allowed attempts in the window
     * @param int    $windowSecs Rolling window in seconds
     * @return bool  true = allowed,  false = rate limited
     */
    public static function check(string $key, int $max = 10, int $windowSecs = 60): bool {
        try {
            $db         = Database::getInstance();
            $ip         = self::getClientIp();
            $identifier = $key . ':' . $ip;
            $now        = date('Y-m-d H:i:s');
            $expiresAt  = date('Y-m-d H:i:s', time() + $windowSecs);

            // Purge expired attempts (keep table lean)
            $db->prepare("DELETE FROM rate_limit_attempts WHERE expires_at < ?")->execute([$now]);

            // Count existing attempts in window
            $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limit_attempts WHERE identifier = ? AND expires_at > ?");
            $stmt->execute([$identifier, $now]);
            $count = (int) $stmt->fetchColumn();

            if ($count >= $max) {
                return false; // Rate limited
            }

            // Record this attempt
            $db->prepare("INSERT INTO rate_limit_attempts (identifier, expires_at) VALUES (?, ?)")
               ->execute([$identifier, $expiresAt]);

            return true;

        } catch (Exception $e) {
            // Fail open — don't block users if rate limiter itself has an error
            error_log('[RateLimit] Error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Respond with 429 and exit if rate limit is exceeded.
     *
     * @param string $key
     * @param int    $max
     * @param int    $windowSecs
     */
    public static function enforce(string $key, int $max = 10, int $windowSecs = 60): void {
        if (!self::check($key, $max, $windowSecs)) {
            http_response_code(429);
            $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
                  || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
            if ($isApi) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Too many requests. Please wait and try again.',
                    'code'    => 429
                ]);
            } else {
                echo '<h2>Too Many Requests</h2><p>Please slow down and try again in a minute.</p>';
            }
            exit;
        }
    }

    /** Best-effort real IP detection (handles proxies) */
    private static function getClientIp(): string {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
