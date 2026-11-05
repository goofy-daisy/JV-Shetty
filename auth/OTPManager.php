<?php
/**
 * OTPManager
 *
 * Handles all OTP lifecycle operations:
 *  - Generation with 10-minute TTL (600 s)
 *  - Redis sliding-window rate limiting (3 OTPs per hour per email)
 *  - Brute-force protection (lock after 5 failed verifications for 1 hour)
 *  - State machine: Idle → OTP Sent → Verifying → Verified | Locked
 *
 * Dependencies: redis PHP extension, Database singleton, constants from
 *               config/redis.php and config/app.php.
 */

class OTPManager {

    // State constants (stored in otp_codes.status)
    const STATE_PENDING  = 'pending';
    const STATE_VERIFIED = 'verified';
    const STATE_EXPIRED  = 'expired';
    const STATE_LOCKED   = 'locked';

    private RedisClientInterface $redis;

    public function __construct(RedisClientInterface $redis) {
        $this->redis = $redis;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Request a new OTP for the given email address.
     *
     * Flow: Idle / previous-expired → OTP Sent
     *
     * @return array{success: bool, message: string, otp?: string}
     *         In production the OTP is delivered by email; the plain OTP is
     *         returned only in the array so that AuthService can hand it to
     *         EmailService asynchronously (keeping response < 2 s).
     */
    public function requestOTP(string $email): array {
        // --- Rate limit: 3 OTP requests per hour (atomic sliding window via Redis Lua) ---
        if (!$this->checkAndIncrementRateLimit($email)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please wait before requesting another OTP.',
                'code'    => 429,
            ];
        }

        // --- Check if the account is locked ---
        if ($this->isAccountLocked($email)) {
            return [
                'success' => false,
                'message' => 'Account is locked due to too many failed attempts. Try again later.',
                'code'    => 423,
            ];
        }

        // --- Expire any still-pending OTPs for this email ---
        $this->expirePendingOTPs($email);

        // --- Generate & store a new OTP ---
        $plainOTP = $this->generateOTP();
        $hash     = password_hash($plainOTP, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', time() + OTP_TTL);

        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at)
            VALUES (?, ?, ?, 0, ?)
        ");
        $stmt->execute([$email, $hash, self::STATE_PENDING, $expiresAt]);

        // --- Audit ---
        $this->logAudit('otp_requested', $email);

        return [
            'success' => true,
            'message' => 'OTP sent to your email address.',
            'otp'     => $plainOTP,   // handed to EmailService by caller
        ];
    }

    /**
     * Verify a plain-text OTP submitted by the user.
     *
     * Flow: OTP Sent → Verifying → Verified | Locked
     *
     * @return array{success: bool, message: string, code?: int}
     */
    public function verifyOTP(string $email, string $plainOTP): array {
        // --- Check if account is locked ---
        if ($this->isAccountLocked($email)) {
            return [
                'success' => false,
                'message' => 'Account is locked. Please try again later.',
                'code'    => 423,
            ];
        }

        $db = Database::getInstance();

        // Fetch the most recent pending OTP for this email
        $stmt = $db->prepare("
            SELECT id, code_hash, expires_at, attempts, status
            FROM   otp_codes
            WHERE  email  = ?
              AND  status = 'pending'
            ORDER  BY created_at DESC
            LIMIT  1
        ");
        $stmt->execute([$email]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return [
                'success' => false,
                'message' => 'No active OTP found. Please request a new one.',
                'code'    => 404,
            ];
        }

        // --- TTL check (strict boundary: expired if current time >= expires_at) ---
        if (strtotime($record['expires_at']) <= time()) {
            $db->prepare("UPDATE otp_codes SET status = 'expired' WHERE id = ?")
               ->execute([$record['id']]);
            $this->logAudit('otp_expired', $email);
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
                'code'    => 410,
            ];
        }

        // --- Verify the code ---
        if (!password_verify($plainOTP, $record['code_hash'])) {
            $attempts = $record['attempts'] + 1;

            if ($attempts >= OTP_MAX_ATTEMPTS) {
                // Lock the OTP record and the user account
                $db->prepare("UPDATE otp_codes SET status = 'locked', attempts = ? WHERE id = ?")
                   ->execute([$attempts, $record['id']]);
                $this->lockAccount($email);
                $this->logAudit('account_locked', $email);

                return [
                    'success' => false,
                    'message' => 'Too many failed attempts. Account locked for 1 hour.',
                    'code'    => 423,
                ];
            }

            $db->prepare("UPDATE otp_codes SET attempts = ? WHERE id = ?")
               ->execute([$attempts, $record['id']]);
            $this->logAudit('otp_failed', $email);

            $remaining = OTP_MAX_ATTEMPTS - $attempts;
            return [
                'success'   => false,
                'message'   => "Invalid OTP. {$remaining} attempt(s) remaining.",
                'code'      => 401,
                'remaining' => $remaining,
            ];
        }

        // --- Success: mark as verified ---
        $db->prepare("UPDATE otp_codes SET status = 'verified', verified_at = NOW() WHERE id = ?")
           ->execute([$record['id']]);

        // Reset login_attempts on the users table
        $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?")
           ->execute([$email]);

        $this->logAudit('otp_verified', $email);

        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
        ];
    }

    /**
     * Return the current state-machine state for an email.
     * Idle | OTP Sent (pending) | Verified | Locked | Expired
     */
    public function getState(string $email): string {
        if ($this->isAccountLocked($email)) {
            return self::STATE_LOCKED;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT status, expires_at
            FROM   otp_codes
            WHERE  email = ?
            ORDER  BY created_at DESC
            LIMIT  1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return 'idle';
        }

        // If the record is pending but TTL has passed, treat as expired
        if ($row['status'] === self::STATE_PENDING && strtotime($row['expires_at']) <= time()) {
            return self::STATE_EXPIRED;
        }

        return $row['status'];
    }

    // =========================================================================
    // Rate Limiting (atomic Redis sliding window via Lua)
    // =========================================================================

    /**
     * Atomically check the sliding-window rate limit AND record the current
     * request in a single Redis round-trip using a Lua script.
     *
     * Returns true  if the request is allowed (counter incremented).
     * Returns false if the rate limit has already been reached (no increment).
     *
     * Using a Lua script eliminates the TOCTOU race that would exist if the
     * check and the increment were two separate commands.
     */
    private function checkAndIncrementRateLimit(string $email): bool {
        $key = 'otp_rate:' . $email;
        $now = time();

        // Lua script: prune stale entries, check count, increment only if under limit.
        $lua = <<<'LUA'
local key          = KEYS[1]
local now          = tonumber(ARGV[1])
local window_start = now - tonumber(ARGV[2])
local limit        = tonumber(ARGV[3])
local window_ttl   = tonumber(ARGV[4])
redis.call('ZREMRANGEBYSCORE', key, '-inf', window_start)
local count = redis.call('ZCARD', key)
if count >= limit then
    return 0
end
redis.call('ZADD', key, now, tostring(now) .. '_' .. tostring(math.random(999999)))
redis.call('EXPIRE', key, window_ttl + 60)
return 1
LUA;

        $result = $this->redis->evalScript($lua, [$key], [
            (string) $now,
            (string) OTP_RATE_WINDOW,
            (string) OTP_RATE_LIMIT,
            (string) OTP_RATE_WINDOW,
        ]);

        return (int) $result === 1;
    }

    /**
     * Check whether this email is below the rate limit (read-only, no increment).
     * Used externally for pre-flight checks; the actual request path uses
     * checkAndIncrementRateLimit() for atomicity.
     */
    public function checkRateLimit(string $email): bool {
        $key    = 'otp_rate:' . $email;
        $now    = time();
        $window = $now - OTP_RATE_WINDOW;

        $this->redis->zRemRangeByScore($key, '-inf', (string) $window);
        $count = $this->redis->zCard($key);
        return $count < OTP_RATE_LIMIT;
    }

    // =========================================================================
    // Brute-force / Account Locking (Redis + DB)
    // =========================================================================

    /**
     * Check if the account is locked (Redis cache or DB fallback).
     */
    public function isAccountLocked(string $email): bool {
        $lockKey = 'otp_lock:' . $email;
        if ($this->redis->exists($lockKey)) {
            return true;
        }

        // Fallback: check the users table locked_until column
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT locked_until FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
            // Re-seed Redis so subsequent calls are fast
            $ttl = strtotime($row['locked_until']) - time();
            $this->redis->setEx($lockKey, $ttl, '1');
            return true;
        }

        return false;
    }

    /**
     * Lock the account for OTP_LOCK_DURATION seconds in both Redis and DB.
     */
    private function lockAccount(string $email): void {
        // Redis
        $lockKey = 'otp_lock:' . $email;
        $this->redis->setEx($lockKey, OTP_LOCK_DURATION, '1');

        // DB (best-effort; user may not exist yet during registration flows)
        try {
            $lockedUntil = date('Y-m-d H:i:s', time() + OTP_LOCK_DURATION);
            $db = Database::getInstance();
            $db->prepare("
                UPDATE users
                SET    login_attempts = ?, locked_until = ?
                WHERE  email = ?
            ")->execute([OTP_MAX_ATTEMPTS, $lockedUntil, $email]);
        } catch (Exception $e) {
            error_log('[OTPManager::lockAccount] ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Generate a cryptographically secure 6-digit OTP.
     */
    private function generateOTP(): string {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Mark all pending OTPs for this email as expired before issuing a new one.
     */
    private function expirePendingOTPs(string $email): void {
        $db = Database::getInstance();
        $db->prepare("UPDATE otp_codes SET status = 'expired' WHERE email = ? AND status = 'pending'")
           ->execute([$email]);
    }

    /**
     * Write to audit_logs.
     */
    private function logAudit(string $action, string $email): void {
        try {
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($ua !== null) {
                $ua = htmlspecialchars(substr($ua, 0, 512), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $db = Database::getInstance();
            $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent)
                SELECT u.id, ?, 'otp', NULL, ?, ?
                FROM   users u
                WHERE  u.email = ?
                LIMIT  1
            ")->execute([
                $action,
                $ip,
                $ua,
                $email,
            ]);
        } catch (Exception $e) {
            error_log('[OTPManager::logAudit] ' . $e->getMessage());
        }
    }
}
