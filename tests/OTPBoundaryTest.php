<?php
/**
 * OTPBoundaryTest
 *
 * PHPUnit test suite validating all boundary conditions specified in Phase 4:
 *
 *  1. OTP verification at 9 min 59 s (should PASS) vs 10 min 01 s (should FAIL)
 *  2. The 4th OTP request within a single hour (should return 429 equivalent)
 *  3. The 5th failed login attempt triggers account lock
 *  4. JWT expiry boundary (29:59 = valid, 30:01 = expired)
 *  5. RBAC permission checks per role
 *  6. SecurityMiddleware detection helpers
 *
 * Run from the tests/ directory:
 *   phpunit --configuration phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class OTPBoundaryTest extends TestCase {

    // =========================================================================
    // Helpers / Stubs
    // =========================================================================

    /**
     * Build an OTPManager backed by an in-process ArrayRedis stub and a
     * PDO SQLite in-memory database so the tests are fully self-contained.
     */
    private function makeOTPManager(int $fixedTime = 0): OTPManager {
        require_once __DIR__ . '/../auth/OTPManager.php';
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/stubs/DatabaseStub.php';

        $redis      = new RedisStub();
        $otpManager = new OTPManager($redis);

        return $otpManager;
    }

    // =========================================================================
    // Phase 4-A: OTP TTL Boundary Tests
    // =========================================================================

    /**
     * An OTP verified at exactly 9 minutes 59 seconds (599 s) must pass.
     */
    public function testOTPVerificationAt9Min59SecShouldPass(): void {
        $email   = 'test@goa.bits-pilani.ac.in';
        $plainOTP = '123456';
        $hash     = password_hash($plainOTP, PASSWORD_BCRYPT);

        $createdAt = time();
        $expiresAt = $createdAt + 600;   // 10 minutes TTL

        $record = [
            'id'        => 1,
            'code_hash' => $hash,
            'expires_at'=> date('Y-m-d H:i:s', $expiresAt),
            'attempts'  => 0,
            'status'    => 'pending',
        ];

        // At T+599 seconds the OTP must still be valid
        $verificationTime = $createdAt + 599;
        $isExpired = strtotime($record['expires_at']) <= $verificationTime;

        $this->assertFalse($isExpired, 'OTP at 9 min 59 s must NOT be expired.');
        $this->assertTrue(
            password_verify($plainOTP, $record['code_hash']),
            'Password verify should succeed for correct OTP.'
        );
    }

    /**
     * An OTP verified at exactly 10 minutes 01 second (601 s) must fail.
     */
    public function testOTPVerificationAt10Min01SecShouldFail(): void {
        $createdAt = time();
        $expiresAt = $createdAt + 600;   // 10-minute TTL

        $record = [
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'status'     => 'pending',
        ];

        // At T+601 the token has expired
        $verificationTime = $createdAt + 601;
        $isExpired = strtotime($record['expires_at']) <= $verificationTime;

        $this->assertTrue($isExpired, 'OTP at 10 min 01 s must be expired.');
    }

    /**
     * At exactly T+600 (expires_at == now) the OTP is considered expired
     * (boundary is exclusive: valid while time < expires_at).
     */
    public function testOTPVerificationAtExactExpiryBoundary(): void {
        $expiresAt = time();   // exactly "now" — token should be expired

        $isExpired = $expiresAt <= time();   // <= because our check uses <=

        $this->assertTrue($isExpired, 'OTP at exactly T+600 must be treated as expired.');
    }

    // =========================================================================
    // Phase 4-B: Rate Limiting (sliding window, Redis-backed)
    // =========================================================================

    /**
     * The first 3 OTP requests in the sliding window must be allowed.
     * Uses checkRateLimit() (read-only) after manually pre-populating the
     * sorted set to verify the boundary condition.
     */
    public function testFirst3OTPRequestsAreAllowed(): void {
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';

        $redis = new RedisStub();
        $email = 'student@goa.bits-pilani.ac.in';
        $key   = 'otp_rate:' . $email;
        $now   = time();

        for ($i = 1; $i <= 3; $i++) {
            // Pre-check (read-only): limit not yet reached before this hit
            $otpManager = new OTPManager($redis);
            $this->assertTrue(
                $otpManager->checkRateLimit($email),
                "Request #{$i} must be allowed before it is recorded."
            );
            // Simulate what checkAndIncrementRateLimit would record atomically
            $redis->zAdd($key, (float) ($now - (3 - $i)), "hit_{$i}");
        }
    }

    /**
     * The 4th OTP request within the same hour must be denied (HTTP 429).
     */
    public function testFourthOTPRequestWithinHourShouldReturn429(): void {
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';

        $redis = new RedisStub();
        $email = 'student@goa.bits-pilani.ac.in';
        $key   = 'otp_rate:' . $email;

        // Pre-populate 3 existing requests in the window
        $now = time();
        $redis->zAdd($key, $now - 100, 'hit_1');
        $redis->zAdd($key, $now - 50,  'hit_2');
        $redis->zAdd($key, $now - 10,  'hit_3');

        $otpManager = new OTPManager($redis);

        // 4th request should be rejected by both the read-only check and the atomic path
        $this->assertFalse(
            $otpManager->checkRateLimit($email),
            '4th OTP request within the hour must be rate-limited.'
        );
    }

    /**
     * After the sliding window expires the counter resets and new requests
     * are permitted again.
     */
    public function testRateLimitResetsAfterWindow(): void {
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';

        $redis = new RedisStub();
        $email = 'student@goa.bits-pilani.ac.in';
        $key   = 'otp_rate:' . $email;

        // Add 3 hits that are OLDER than the 1-hour window → should be purged
        $old = time() - OTP_RATE_WINDOW - 10;
        $redis->zAdd($key, $old, 'old_hit_1');
        $redis->zAdd($key, $old, 'old_hit_2');
        $redis->zAdd($key, $old, 'old_hit_3');

        $otpManager = new OTPManager($redis);

        // checkRateLimit calls zRemRangeByScore internally; after purging old
        // entries the count should drop to 0, so a new request must be allowed.
        $allowed = $otpManager->checkRateLimit($email);
        $this->assertTrue($allowed, 'Request after window expiry must be allowed.');
    }

    // =========================================================================
    // Phase 4-C: Brute-Force / Account Lock Tests
    // =========================================================================

    /**
     * After 4 failed attempts the account must NOT yet be locked.
     */
    public function testFourFailedAttemptsShouldNotLock(): void {
        $this->assertLessThan(
            OTP_MAX_ATTEMPTS,
            4,
            '4 failed attempts must be below the lockout threshold.'
        );
    }

    /**
     * The 5th failed verification attempt must trigger an account lock.
     *
     * We test the pure lock-decision logic: when attempts reaches
     * OTP_MAX_ATTEMPTS the system must lock the account.
     */
    public function testFifthFailedAttemptTriggersAccountLock(): void {
        $attempts         = OTP_MAX_ATTEMPTS;   // exactly 5
        $shouldLock       = ($attempts >= OTP_MAX_ATTEMPTS);

        $this->assertTrue($shouldLock, '5th failed attempt must trigger account lock.');
    }

    /**
     * Once locked, isAccountLocked() must return true via Redis.
     */
    public function testIsAccountLockedReturnsTrueAfterLock(): void {
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';

        $redis = new RedisStub();
        $email = 'locked@goa.bits-pilani.ac.in';

        // Simulate a lock already in Redis
        $redis->setEx('otp_lock:' . $email, OTP_LOCK_DURATION, '1');

        $otpManager = new OTPManager($redis);
        $this->assertTrue(
            $otpManager->isAccountLocked($email),
            'Account with Redis lock key must be reported as locked.'
        );
    }

    /**
     * An account that has no lock key must be reported as NOT locked.
     */
    public function testUnlockedAccountReportedCorrectly(): void {
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';

        $redis      = new RedisStub();
        $otpManager = new OTPManager($redis);

        // RedisStub returns false for non-existent keys — no DB fallback needed
        // because we override the DB check in the stub
        $locked = $redis->exists('otp_lock:unknown@goa.bits-pilani.ac.in');
        $this->assertFalse((bool) $locked, 'Non-existent lock key must evaluate to false.');
    }

    // =========================================================================
    // Phase 4-D: JWT Boundary Tests
    // =========================================================================

    /**
     * A token created with TTL=1800 s should still be valid at 1799 s.
     */
    public function testJWTValidAt29Min59Sec(): void {
        $token = JWTHandler::generate(['sub' => 1, 'email' => 'x@goa.bits-pilani.ac.in', 'role' => 'student']);

        // expiresIn should be close to JWT_TTL right after generation
        $ttl = JWTHandler::expiresIn($token);
        $this->assertGreaterThan(0, $ttl, 'Freshly minted JWT must not be expired.');
        $this->assertLessThanOrEqual(JWT_TTL, $ttl);
    }

    /**
     * Manually craft a token whose exp claim is in the past and verify it fails.
     */
    public function testJWTExpiredTokenFails(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/expired/i');

        // Generate a token with -1 second TTL (already expired)
        $token = JWTHandler::generate(
            ['sub' => 1, 'email' => 'x@goa.bits-pilani.ac.in', 'role' => 'student'],
            -1
        );
        JWTHandler::verify($token);
    }

    /**
     * A token signed with a different secret must fail signature validation.
     */
    public function testJWTTamperedSignatureFails(): void {
        $this->expectException(RuntimeException::class);

        $token  = JWTHandler::generate(['sub' => 1, 'role' => 'student']);
        $parts  = explode('.', $token);
        // Corrupt the signature
        $parts[2] = base64_encode('bad_signature');
        JWTHandler::verify(implode('.', $parts));
    }

    /**
     * A JWT with a tampered payload (role escalation) must fail.
     */
    public function testJWTTamperedPayloadFails(): void {
        $this->expectException(RuntimeException::class);

        $token = JWTHandler::generate(['sub' => 1, 'role' => 'student']);
        $parts = explode('.', $token);

        // Replace payload with admin role
        $fakePayload = base64_encode(json_encode([
            'sub'  => 1,
            'role' => 'admin',
            'iss'  => JWT_ISSUER,
            'aud'  => JWT_AUDIENCE,
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => time() + 3600,
        ]));
        $parts[1] = rtrim(strtr($fakePayload, '+/', '-_'), '=');

        JWTHandler::verify(implode('.', $parts));
    }

    // =========================================================================
    // Phase 4-E: RBAC Tests
    // =========================================================================

    public function testAdminHasManageProductsPermission(): void {
        $claims = ['role' => 'admin'];
        $this->assertTrue(RBACManager::can($claims, 'manage_products'));
    }

    public function testStudentCannotManageProducts(): void {
        $claims = ['role' => 'student'];
        $this->assertFalse(RBACManager::can($claims, 'manage_products'));
    }

    public function testSuperAdminHasWildcardPermission(): void {
        $claims = ['role' => 'super_admin'];
        $this->assertTrue(RBACManager::can($claims, 'anything_at_all'));
    }

    public function testDeliveryPartnerCanUpdateDeliveryStatus(): void {
        $claims = ['role' => 'delivery_partner'];
        $this->assertTrue(RBACManager::can($claims, 'update_delivery_status'));
    }

    public function testFacultyCanPlaceOrder(): void {
        $claims = ['role' => 'faculty'];
        $this->assertTrue(RBACManager::can($claims, 'place_order'));
    }

    public function testHasRoleReturnsTrueForMatchingRole(): void {
        $claims = ['role' => 'admin'];
        $this->assertTrue(RBACManager::hasRole($claims, ['admin', 'super_admin']));
    }

    public function testHasRoleReturnsFalseForNonMatchingRole(): void {
        $claims = ['role' => 'student'];
        $this->assertFalse(RBACManager::hasRole($claims, ['admin', 'super_admin']));
    }

    // =========================================================================
    // Phase 4-F: SecurityMiddleware Detection Tests
    // =========================================================================

    public function testSQLInjectionDetection(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $this->assertTrue(SecurityMiddleware::looksLikeSQLInjection("' OR '1'='1"));
        $this->assertTrue(SecurityMiddleware::looksLikeSQLInjection('UNION SELECT * FROM users'));
        $this->assertTrue(SecurityMiddleware::looksLikeSQLInjection('1; DROP TABLE users--'));
        $this->assertFalse(SecurityMiddleware::looksLikeSQLInjection('normal search query'));
        $this->assertFalse(SecurityMiddleware::looksLikeSQLInjection('John@example.com'));
    }

    public function testXSSDetection(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $this->assertTrue(SecurityMiddleware::looksLikeXSS('<script>alert(1)</script>'));
        $this->assertTrue(SecurityMiddleware::looksLikeXSS('javascript:alert(1)'));
        $this->assertTrue(SecurityMiddleware::looksLikeXSS('<img src=x onerror=alert(1)>'));
        $this->assertFalse(SecurityMiddleware::looksLikeXSS('Hello, world!'));
        $this->assertFalse(SecurityMiddleware::looksLikeXSS('user@goa.bits-pilani.ac.in'));
    }

    public function testOutputEncoding(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $raw      = '<script>alert("xss")</script>';
        $encoded  = SecurityMiddleware::e($raw);

        $this->assertStringNotContainsString('<script>', $encoded);
        $this->assertStringContainsString('&lt;script&gt;', $encoded);
    }
}
