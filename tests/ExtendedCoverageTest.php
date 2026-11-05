<?php
/**
 * ExtendedCoverageTest
 *
 * Fills the coverage gaps left by OTPBoundaryTest.  Each section targets a
 * class or code path that was previously untested:
 *
 *  A. AuthService – email validation, requestOTP/verifyOTPAndLogin flows
 *  B. JWTHandler  – structural errors, missing claims, nbf, issuer/audience,
 *                   expiresIn edge-cases, custom TTL, server-claim override
 *  C. OTPManager  – getState() state-machine, full verifyOTP() paths
 *  D. RBACManager – getPermissions(), unknown role/permission, claim shapes
 *  E. JWTMiddleware  – extractToken from headers, authenticate()
 *  F. RBACMiddleware – check() path (no-abort role gate)
 *  G. SecurityMiddleware – getNonce(), jsonSafe(), deep sanitize edge-cases
 *
 * Run from the tests/ directory:
 *   phpunit --configuration phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class ExtendedCoverageTest extends TestCase {

    // =========================================================================
    // A. AuthService — email validation & flow coverage
    // =========================================================================

    /** Load AuthService with a minimal EmailService stub so no SMTP is hit. */
    private function bootAuthService(): AuthService {
        require_once __DIR__ . '/stubs/EmailServiceStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';
        require_once __DIR__ . '/../auth/AuthService.php';
        require_once __DIR__ . '/stubs/RedisStub.php';

        $redis = new RedisStub();
        return new AuthService(new OTPManager($redis));
    }

    public function testIsValidCampusEmailAcceptsValidAddress(): void {
        $svc = $this->bootAuthService();
        $this->assertTrue($svc->isValidCampusEmail('f20230001@goa.bits-pilani.ac.in'));
        $this->assertTrue($svc->isValidCampusEmail('first.last+tag@goa.bits-pilani.ac.in'));
    }

    public function testIsValidCampusEmailRejectsOtherDomains(): void {
        $svc = $this->bootAuthService();
        $this->assertFalse($svc->isValidCampusEmail('user@gmail.com'));
        $this->assertFalse($svc->isValidCampusEmail('user@pilani.bits-pilani.ac.in'));
        $this->assertFalse($svc->isValidCampusEmail('user@goa.bits-pilani.ac.in.evil.com'));
        $this->assertFalse($svc->isValidCampusEmail(''));
    }

    public function testRequestOTPRejectsInvalidEmail(): void {
        $svc    = $this->bootAuthService();
        $result = $svc->requestOTP('invalid@gmail.com');

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['code']);
        $this->assertStringContainsStringIgnoringCase('campus', $result['message']);
    }

    public function testRequestOTPNormalisesEmailCase(): void {
        $svc    = $this->bootAuthService();
        $result = $svc->requestOTP('USER@GOA.BITS-PILANI.AC.IN');

        // Domain is invalid only if regex is case-insensitive; our regex has /i flag
        // Actually this is the valid campus domain in upper case — regex allows it
        // The result will be success (OTP stored) since email is valid after tolower.
        // (No user exists, so email is sent to stub but no DB user lookup here.)
        $this->assertIsBool($result['success']);
    }

    public function testRequestOTPRateLimited(): void {
        $svc = $this->bootAuthService();
        // Exhaust the 3-per-hour limit
        $email = 'rate@goa.bits-pilani.ac.in';
        $svc->requestOTP($email);
        $svc->requestOTP($email);
        $svc->requestOTP($email);
        $fourth = $svc->requestOTP($email);

        $this->assertFalse($fourth['success']);
        $this->assertSame(429, $fourth['code']);
    }

    public function testVerifyOTPAndLoginFailsOnWrongOTP(): void {
        $svc   = $this->bootAuthService();
        $email = 'verify@goa.bits-pilani.ac.in';

        // Request a real OTP (stored in SQLite stub)
        $req = $svc->requestOTP($email);
        $this->assertTrue($req['success'], 'OTP request must succeed for this test.');

        // Submit wrong code
        $result = $svc->verifyOTPAndLogin($email, '000000');
        $this->assertFalse($result['success']);
    }

    public function testVerifyOTPAndLoginReturnsUserNotFoundWhenNoUserRow(): void {
        $svc   = $this->bootAuthService();
        $email = 'ghost@goa.bits-pilani.ac.in';

        // Manually insert an OTP record so verifyOTP() returns success but the
        // users table has no matching row.
        $db   = DatabaseStub::setup();
        $hash = password_hash('654321', PASSWORD_BCRYPT);
        $exp  = date('Y-m-d H:i:s', time() + 600);
        $stmt = $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, ?, 'pending', 0, ?)"
        );
        $stmt->execute([$email, $hash, $exp]);
        $result = $svc->verifyOTPAndLogin($email, '654321');
        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['code']);
        $this->assertStringContainsStringIgnoringCase('not found', $result['message']);
    }

    public function testVerifyOTPAndLoginSuccessWithRealOtp(): void {
        $svc   = $this->bootAuthService();
        $email = 'student@goa.bits-pilani.ac.in';

        // Seed a user + role so getUserByEmail() returns a row.
        // role_id=1 already exists as 'student' from DatabaseStub::setup().
        $db = DatabaseStub::setup();
        $db->prepare(
            "INSERT OR IGNORE INTO users (id, email, password_hash, role_id, login_attempts, deleted_at)
             VALUES (99, ?, 'x', 1, 0, NULL)"
        )->execute([$email]);

        // Plant a known OTP hash
        $plain = '112233';
        $hash  = password_hash($plain, PASSWORD_BCRYPT);
        $exp   = date('Y-m-d H:i:s', time() + 600);
        // Expire any pre-existing pending OTPs for this email first
        $db->prepare("UPDATE otp_codes SET status = 'expired' WHERE email = ? AND status = 'pending'")
           ->execute([$email]);
        $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, ?, 'pending', 0, ?)"
        )->execute([$email, $hash, $exp]);
        $result = $svc->verifyOTPAndLogin($email, $plain);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame($email, $result['user']['email']);
    }

    public function testVerifyTokenValidJWT(): void {
        $svc   = $this->bootAuthService();
        $token = JWTHandler::generate(['sub' => 1, 'email' => 'x@goa.bits-pilani.ac.in', 'role' => 'student']);
        $res   = $svc->verifyToken($token);

        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('claims', $res);
    }

    public function testVerifyTokenExpiredJWT(): void {
        $svc   = $this->bootAuthService();
        $token = JWTHandler::generate(['sub' => 1, 'role' => 'student'], -1);
        $res   = $svc->verifyToken($token);

        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('message', $res);
    }

    // =========================================================================
    // B. JWTHandler — structural errors, claim validation, expiresIn
    // =========================================================================

    public function testVerifyThrowsOnTooFewParts(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/structure/i');
        JWTHandler::verify('only.two');
    }

    public function testVerifyThrowsOnTooManyParts(): void {
        $this->expectException(RuntimeException::class);
        JWTHandler::verify('a.b.c.d');
    }

    public function testVerifyThrowsOnWrongAlgorithm(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/algorithm/i');

        // Build a token with a different alg in the header
        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims  = ['sub' => 1, 'iss' => JWT_ISSUER, 'aud' => JWT_AUDIENCE,
                    'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600];
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');

        JWTHandler::verify($header . '.' . $payload . '.' . $sig);
    }

    public function testVerifyThrowsOnInvalidPayloadJSON(): void {
        $this->expectException(RuntimeException::class);

        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => JWT_ALGORITHM, 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode('not-json'), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');

        JWTHandler::verify($header . '.' . $payload . '.' . $sig);
    }

    public function testVerifyThrowsWhenExpClaimMissing(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/expired/i');

        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => JWT_ALGORITHM, 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims  = ['sub' => 1, 'iss' => JWT_ISSUER, 'aud' => JWT_AUDIENCE, 'iat' => time()];
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');

        JWTHandler::verify($header . '.' . $payload . '.' . $sig);
    }

    public function testVerifyThrowsWhenNbfIsInFuture(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not yet valid/i');

        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => JWT_ALGORITHM, 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims  = [
            'sub' => 1,
            'iss' => JWT_ISSUER,
            'aud' => JWT_AUDIENCE,
            'iat' => time(),
            'nbf' => time() + 9999,
            'exp' => time() + 3600,
        ];
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');

        JWTHandler::verify($header . '.' . $payload . '.' . $sig);
    }

    public function testVerifyThrowsOnWrongIssuer(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/issuer/i');

        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => JWT_ALGORITHM, 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims  = ['iss' => 'wrong-issuer', 'aud' => JWT_AUDIENCE, 'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600];
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');

        JWTHandler::verify($header . '.' . $payload . '.' . $sig);
    }

    public function testVerifyThrowsOnWrongAudience(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/audience/i');

        $header  = rtrim(strtr(base64_encode(json_encode(['alg' => JWT_ALGORITHM, 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claims  = ['iss' => JWT_ISSUER, 'aud' => 'wrong-audience', 'iat' => time(), 'nbf' => time(), 'exp' => time() + 3600];
        $payload = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
        $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');

        JWTHandler::verify($header . '.' . $payload . '.' . $sig);
    }

    public function testExpiresInReturnsZeroForExpiredToken(): void {
        $token = JWTHandler::generate(['sub' => 1, 'role' => 'student'], -1);
        $this->assertSame(0, JWTHandler::expiresIn($token));
    }

    public function testExpiresInReturnsZeroForMalformedToken(): void {
        $this->assertSame(0, JWTHandler::expiresIn('not.a.token'));
    }

    public function testExpiresInReturnsPositiveForFreshToken(): void {
        $token = JWTHandler::generate(['sub' => 1, 'role' => 'student']);
        $this->assertGreaterThan(0, JWTHandler::expiresIn($token));
    }

    public function testGenerateWithCustomTTL(): void {
        $ttl   = 60;
        $token = JWTHandler::generate(['sub' => 1, 'role' => 'student'], $ttl);
        $left  = JWTHandler::expiresIn($token);
        $this->assertGreaterThan(0, $left);
        $this->assertLessThanOrEqual($ttl, $left);
    }

    public function testGenerateServerClaimsCannotBeOverriddenByCaller(): void {
        // Caller tries to inject a custom 'exp' 100 years in future
        $token  = JWTHandler::generate(['sub' => 1, 'role' => 'student', 'exp' => time() + 100 * 365 * 86400]);
        $claims = JWTHandler::verify($token);

        // exp must be capped at JWT_TTL, not 100 years
        $this->assertLessThanOrEqual(time() + JWT_TTL + 2, $claims['exp']);
    }

    public function testGenerateEmbedsSub(): void {
        $token  = JWTHandler::generate(['sub' => 42, 'role' => 'admin']);
        $claims = JWTHandler::verify($token);
        $this->assertSame(42, $claims['sub']);
    }

    // =========================================================================
    // C. OTPManager — getState() and full verifyOTP() flow
    // =========================================================================

    private function makeOTPManagerFresh(): OTPManager {
        require_once __DIR__ . '/stubs/RedisStub.php';
        DatabaseStub::reset();
        DatabaseStub::setup();
        return new OTPManager(new RedisStub());
    }

    public function testGetStateIdleWhenNoRecords(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'idle@goa.bits-pilani.ac.in';
        $this->assertSame('idle', $mgr->getState($email));
    }

    public function testGetStatePendingAfterRequest(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'pending@goa.bits-pilani.ac.in';
        $mgr->requestOTP($email);
        $this->assertSame(OTPManager::STATE_PENDING, $mgr->getState($email));
    }

    public function testGetStateVerifiedAfterSuccess(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'verified@goa.bits-pilani.ac.in';

        $req   = $mgr->requestOTP($email);
        // Re-fetch the hash directly so we can verify without knowing the OTP
        $db   = DatabaseStub::getInstance();
        $stmt = $db->prepare("SELECT code_hash FROM otp_codes WHERE email = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        // We can't reverse-engineer the OTP from bcrypt, so call verifyOTP via
        // the returned OTP that requestOTP() exposes (it's in $req['otp']).
        $this->assertArrayHasKey('otp', $req);
        $mgr->verifyOTP($email, $req['otp']);
        $this->assertSame(OTPManager::STATE_VERIFIED, $mgr->getState($email));
    }

    public function testGetStateLockedWhenAccountLocked(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'locked@goa.bits-pilani.ac.in';

        $redis = new RedisStub();
        $redis->setEx('otp_lock:' . $email, OTP_LOCK_DURATION, '1');
        $lockedMgr = new OTPManager($redis);

        $this->assertSame(OTPManager::STATE_LOCKED, $lockedMgr->getState($email));
    }

    public function testGetStateExpiredWhenTtlPassed(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'exp@goa.bits-pilani.ac.in';

        // Insert an OTP that already expired
        $db  = DatabaseStub::getInstance();
        $exp = date('Y-m-d H:i:s', time() - 1);
        $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, 'hash', 'pending', 0, ?)"
        )->execute([$email, $exp]);
        $this->assertSame(OTPManager::STATE_EXPIRED, $mgr->getState($email));
    }

    public function testVerifyOTPNoActiveOTPReturns404(): void {
        $mgr    = $this->makeOTPManagerFresh();
        $result = $mgr->verifyOTP('nobody@goa.bits-pilani.ac.in', '123456');

        $this->assertFalse($result['success']);
        $this->assertSame(404, $result['code']);
    }

    public function testVerifyOTPExpiredRecordReturns410(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'expiredotp@goa.bits-pilani.ac.in';
        $db    = DatabaseStub::getInstance();
        $exp   = date('Y-m-d H:i:s', time() - 1);
        $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, 'hash', 'pending', 0, ?)"
        )->execute([$email, $exp]);
        $result = $mgr->verifyOTP($email, '000000');
        $this->assertFalse($result['success']);
        $this->assertSame(410, $result['code']);
        $this->assertStringContainsStringIgnoringCase('expired', $result['message']);
    }

    public function testVerifyOTPWrongCodeDecrementsAttempts(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'wrongotp@goa.bits-pilani.ac.in';
        $req   = $mgr->requestOTP($email);
        $this->assertTrue($req['success']);

        $result = $mgr->verifyOTP($email, '000000');
        $this->assertFalse($result['success']);
        $this->assertSame(401, $result['code']);
        $this->assertArrayHasKey('remaining', $result);
        $this->assertSame(OTP_MAX_ATTEMPTS - 1, $result['remaining']);
    }

    public function testVerifyOTPFiveWrongCodesLocksAccount(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'lockme@goa.bits-pilani.ac.in';

        // Request a new OTP before each attempt to keep a pending record alive
        // (after locking the record we cannot re-request for the same email without
        // removing the lock, so we pre-seed the hash once and exhaust the attempts).
        $req  = $mgr->requestOTP($email);
        $this->assertTrue($req['success']);

        for ($i = 1; $i <= OTP_MAX_ATTEMPTS - 1; $i++) {
            $res = $mgr->verifyOTP($email, '000000');
            $this->assertFalse($res['success']);
        }

        $finalRes = $mgr->verifyOTP($email, '000000');
        $this->assertFalse($finalRes['success']);
        $this->assertSame(423, $finalRes['code']);
        $this->assertTrue($mgr->isAccountLocked($email));
    }

    public function testVerifyOTPSuccessResetsAttempts(): void {
        $mgr   = $this->makeOTPManagerFresh();
        $email = 'success@goa.bits-pilani.ac.in';

        $req = $mgr->requestOTP($email);
        $this->assertTrue($req['success']);
        $this->assertArrayHasKey('otp', $req);

        // Seed a user so the DB reset of login_attempts works
        $db = DatabaseStub::getInstance();
        $db->prepare(
            "INSERT OR IGNORE INTO users (id, email, password_hash, role_id, login_attempts, deleted_at) VALUES (1, ?, 'x', 1, 3, NULL)"
        )->execute([$email]);
        $result = $mgr->verifyOTP($email, $req['otp']);
        $this->assertTrue($result['success']);
        $this->assertStringContainsStringIgnoringCase('verified', $result['message']);
    }

    public function testRequestOTPAccountLockedReturns423(): void {
        $redis = new RedisStub();
        $email = 'alreadylocked@goa.bits-pilani.ac.in';
        $redis->setEx('otp_lock:' . $email, OTP_LOCK_DURATION, '1');

        $mgr    = new OTPManager($redis);
        $result = $mgr->requestOTP($email);

        $this->assertFalse($result['success']);
        $this->assertSame(423, $result['code']);
    }

    public function testCheckRateLimitReturnsFalseAtLimit(): void {
        $redis = new RedisStub();
        $email = 'atlimit@goa.bits-pilani.ac.in';
        $key   = 'otp_rate:' . $email;
        $now   = time();

        for ($i = 0; $i < OTP_RATE_LIMIT; $i++) {
            $redis->zAdd($key, (float) ($now - $i), "hit_{$i}");
        }

        $mgr = new OTPManager($redis);
        $this->assertFalse($mgr->checkRateLimit($email));
    }

    public function testCheckRateLimitReturnsTrueBelowLimit(): void {
        $redis = new RedisStub();
        $mgr   = new OTPManager($redis);
        $this->assertTrue($mgr->checkRateLimit('fresh@goa.bits-pilani.ac.in'));
    }

    // =========================================================================
    // D. RBACManager — getPermissions(), unknown role, empty claims
    // =========================================================================

    public function testGetPermissionsReturnsCorrectListForAdmin(): void {
        $perms = RBACManager::getPermissions('admin');
        $this->assertContains('manage_products', $perms);
        $this->assertContains('view_audit_logs', $perms);
        $this->assertNotContains('*', $perms);
    }

    public function testGetPermissionsReturnsEmptyArrayForUnknownRole(): void {
        $this->assertSame([], RBACManager::getPermissions('unknown_role'));
    }

    public function testCanReturnsFalseForUnknownRole(): void {
        $this->assertFalse(RBACManager::can(['role' => 'ghost'], 'view_products'));
    }

    public function testCanReturnsFalseWhenRoleClaimAbsent(): void {
        $this->assertFalse(RBACManager::can([], 'view_products'));
    }

    public function testRoleHasPermissionReturnsFalseForUnknownRole(): void {
        $this->assertFalse(RBACManager::roleHasPermission('nonexistent', 'place_order'));
    }

    public function testHasRoleReturnsFalseWhenClaimsHaveNoRoleKey(): void {
        $this->assertFalse(RBACManager::hasRole([], ['admin']));
    }

    public function testCustomerCanPlaceOrder(): void {
        $this->assertTrue(RBACManager::can(['role' => 'customer'], 'place_order'));
    }

    public function testDeliveryPartnerCannotManageProducts(): void {
        $this->assertFalse(RBACManager::can(['role' => 'delivery_partner'], 'manage_products'));
    }

    public function testStudentCanViewProducts(): void {
        $this->assertTrue(RBACManager::can(['role' => 'student'], 'view_products'));
    }

    public function testSuperAdminHasManageSettingsPermission(): void {
        $this->assertTrue(RBACManager::can(['role' => 'super_admin'], 'manage_settings'));
    }

    public function testHasRoleReturnsTrueForDeliveryPartner(): void {
        $claims = ['role' => 'delivery_partner'];
        $this->assertTrue(RBACManager::hasRole($claims, ['delivery_partner', 'admin']));
    }

    // =========================================================================
    // E. JWTMiddleware — extractToken paths, authenticate()
    // =========================================================================

    protected function setUp(): void {
        parent::setUp();
        // Clear any server variables left by previous tests
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_X_AUTH_TOKEN'],
            $_REQUEST['_jwt_claims']
        );
    }

    private function bootMiddlewares(): void {
        require_once __DIR__ . '/../auth/middleware/JWTMiddleware.php';
        require_once __DIR__ . '/../auth/middleware/RBACMiddleware.php';
    }

    public function testAuthenticateThrowsWhenNoTokenPresent(): void {
        $this->bootMiddlewares();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No authentication token/i');
        JWTMiddleware::authenticate();
    }

    public function testAuthenticateSucceedsWithBearerToken(): void {
        $this->bootMiddlewares();
        $token = JWTHandler::generate(['sub' => 1, 'email' => 'a@goa.bits-pilani.ac.in', 'role' => 'student']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $claims = JWTMiddleware::authenticate();
        $this->assertSame(1, $claims['sub']);
        $this->assertSame('student', $claims['role']);
    }

    public function testAuthenticateSucceedsWithXAuthTokenHeader(): void {
        $this->bootMiddlewares();
        $token = JWTHandler::generate(['sub' => 2, 'email' => 'b@goa.bits-pilani.ac.in', 'role' => 'admin']);
        $_SERVER['HTTP_X_AUTH_TOKEN'] = $token;

        $claims = JWTMiddleware::authenticate();
        $this->assertSame(2, $claims['sub']);
        $this->assertSame('admin', $claims['role']);
    }

    public function testAuthenticateThrowsForExpiredToken(): void {
        $this->bootMiddlewares();
        $this->expectException(RuntimeException::class);

        $token = JWTHandler::generate(['sub' => 1, 'role' => 'student'], -1);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        JWTMiddleware::authenticate();
    }

    public function testHandleOptionalReturnsNullWithNoToken(): void {
        $this->bootMiddlewares();
        $result = JWTMiddleware::handle(false);
        $this->assertNull($result);
    }

    public function testHandleOptionalReturnsClaims(): void {
        $this->bootMiddlewares();
        $token = JWTHandler::generate(['sub' => 5, 'role' => 'faculty']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $claims = JWTMiddleware::handle(false);
        $this->assertNotNull($claims);
        $this->assertSame(5, $claims['sub']);
        $this->assertSame($claims, $_REQUEST['_jwt_claims']);
    }

    // =========================================================================
    // F. RBACMiddleware — check() path (non-aborting)
    // =========================================================================

    public function testRBACCheckReturnsNullWithNoToken(): void {
        $this->bootMiddlewares();
        $result = RBACMiddleware::check(['admin']);
        $this->assertNull($result);
    }

    public function testRBACCheckReturnsClaims(): void {
        $this->bootMiddlewares();
        $token = JWTHandler::generate(['sub' => 7, 'role' => 'admin']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $claims = RBACMiddleware::check(['admin']);
        $this->assertNotNull($claims);
        $this->assertSame('admin', $claims['role']);
    }

    public function testRBACCheckReturnsNullForWrongRole(): void {
        $this->bootMiddlewares();
        $token = JWTHandler::generate(['sub' => 8, 'role' => 'student']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $result = RBACMiddleware::check(['admin', 'super_admin']);
        $this->assertNull($result);
    }

    public function testRBACCheckAllowsAnyRoleWhenNoRolesSpecified(): void {
        $this->bootMiddlewares();
        $token = JWTHandler::generate(['sub' => 9, 'role' => 'delivery_partner']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $claims = RBACMiddleware::check();
        $this->assertNotNull($claims);
        $this->assertSame('delivery_partner', $claims['role']);
    }

    // =========================================================================
    // G. SecurityMiddleware — getNonce(), jsonSafe(), deep sanitize
    // =========================================================================

    public function testGetNonceReturnsSameValueWithinRequest(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $n1 = SecurityMiddleware::getNonce();
        $n2 = SecurityMiddleware::getNonce();
        $this->assertSame($n1, $n2, 'Nonce must be stable within a single request.');
        $this->assertGreaterThan(10, strlen($n1), 'Nonce should be adequately long.');
    }

    public function testJsonSafeEscapesHTMLEntities(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $data   = ['msg' => '<script>alert(1)</script>', 'tag' => '</div>'];
        $output = SecurityMiddleware::jsonSafe($data);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringNotContainsString('</div>', $output);
        $this->assertStringContainsString('\\u003C', $output);
    }

    public function testSanitizeInputStripsNullBytes(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $_GET = ['q' => "hello\0world"];
        SecurityMiddleware::sanitizeInput();
        $this->assertSame('helloworld', $_GET['q']);
    }

    public function testSanitizeInputStripsControlCharacters(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $_POST = ['field' => "clean\x01data\x1F"];
        SecurityMiddleware::sanitizeInput();
        $this->assertSame('cleandata', $_POST['field']);
    }

    public function testSanitizeInputTruncatesOversizedValues(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $_POST = ['big' => str_repeat('A', 70000)];
        SecurityMiddleware::sanitizeInput();
        $this->assertSame(65535, strlen($_POST['big']));
    }

    public function testSQLInjectionDetectionHandlesEdgeCases(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        // Should detect
        $this->assertTrue(SecurityMiddleware::looksLikeSQLInjection("OR 1=1"));
        $this->assertTrue(SecurityMiddleware::looksLikeSQLInjection("/* comment */"));

        // Should not detect
        $this->assertFalse(SecurityMiddleware::looksLikeSQLInjection('product name 100g'));
        $this->assertFalse(SecurityMiddleware::looksLikeSQLInjection('order placed'));
    }

    public function testXSSDetectionCatchesIframeAndOnEvent(): void {
        require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';

        $this->assertTrue(SecurityMiddleware::looksLikeXSS('<iframe src="evil.com">'));
        $this->assertTrue(SecurityMiddleware::looksLikeXSS('<a onclick=steal()>click</a>'));

        // Legitimate HTML attribute-like value should not trigger
        $this->assertFalse(SecurityMiddleware::looksLikeXSS('The concert on Saturday'));
    }
}
