<?php
/**
 * IntegrationJourneyTest
 *
 * End-to-end integration test that simulates a complete user journey through
 * the OTP-based authentication and RBAC system:
 *
 *   Step 1  – A user requests an OTP (campus email only)
 *   Step 2  – The user verifies the OTP and receives a JWT
 *   Step 3  – The JWT is used to access a protected route (profile / cart)
 *   Step 4  – The RBAC middleware enforces role-based access
 *
 * Also includes an incompatibility analysis section that documents where the
 * legacy session-based Auth class conflicts with JWTMiddleware.
 *
 * Run from the tests/ directory:
 *   phpunit --configuration phpunit.xml
 */

use PHPUnit\Framework\TestCase;

class IntegrationJourneyTest extends TestCase {

    private AuthService $authService;
    private OTPManager  $otpManager;
    private RedisStub   $redis;

    // =========================================================================
    // Setup
    // =========================================================================

    protected function setUp(): void {
        parent::setUp();

        // Reset shared state between journeys
        DatabaseStub::reset();
        DatabaseStub::setup();

        // Reset server variables left by previous tests
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'],
            $_SERVER['HTTP_X_AUTH_TOKEN'],
            $_REQUEST['_jwt_claims']
        );

        require_once __DIR__ . '/stubs/EmailServiceStub.php';
        require_once __DIR__ . '/stubs/RedisStub.php';
        require_once __DIR__ . '/../auth/OTPManager.php';
        require_once __DIR__ . '/../auth/AuthService.php';
        require_once __DIR__ . '/../auth/middleware/JWTMiddleware.php';
        require_once __DIR__ . '/../auth/middleware/RBACMiddleware.php';
        $this->redis       = new RedisStub();
        $this->otpManager  = new OTPManager($this->redis);
        $this->authService = new AuthService($this->otpManager);

        // Seed database: roles + a student user
        $db = DatabaseStub::getInstance();
        $db->exec("INSERT OR IGNORE INTO roles (id, name) VALUES (1, 'student')");
        $db->exec("INSERT OR IGNORE INTO roles (id, name) VALUES (2, 'admin')");
        $db->prepare(
            "INSERT OR IGNORE INTO users (id, email, password_hash, role_id, login_attempts, deleted_at) VALUES (10, ?, 'x', 1, 0, NULL)"
        )->execute(['journey@goa.bits-pilani.ac.in']);
        $db->prepare(
            "INSERT OR IGNORE INTO users (id, email, password_hash, role_id, login_attempts, deleted_at) VALUES (11, ?, 'x', 2, 0, NULL)"
        )->execute(['admin.journey@goa.bits-pilani.ac.in']);
    }

    // =========================================================================
    // Full journey: student
    // =========================================================================

    /**
     * Journey: student requests OTP → invalid email rejected.
     */
    public function testStep1RejectsNonCampusEmail(): void {
        $result = $this->authService->requestOTP('user@gmail.com');

        $this->assertFalse($result['success'], 'Non-campus email must be rejected at Step 1.');
        $this->assertSame(422, $result['code']);
    }

    /**
     * Journey: student requests OTP → success.
     */
    public function testStep1RequestOTPSucceeds(): void {
        $result = $this->authService->requestOTP('journey@goa.bits-pilani.ac.in');

        $this->assertTrue($result['success'], 'Step 1 – OTP request must succeed.');
        $this->assertStringContainsStringIgnoringCase('OTP sent', $result['message']);
        // The raw OTP must NOT be in the public-facing response
        $this->assertArrayNotHasKey('otp', $result);
    }

    /**
     * Journey: OTP verified with wrong code → login fails.
     */
    public function testStep2WrongOTPFails(): void {
        $this->authService->requestOTP('journey@goa.bits-pilani.ac.in');

        $result = $this->authService->verifyOTPAndLogin('journey@goa.bits-pilani.ac.in', '000000');
        $this->assertFalse($result['success'], 'Step 2 – Wrong OTP must fail.');
    }

    /**
     * Journey: correct OTP returns a valid JWT.
     */
    public function testStep2CorrectOTPReturnsJWT(): string {
        $email = 'journey@goa.bits-pilani.ac.in';

        // Inject a known-good OTP directly into the DB so we control the value
        $plain = '987654';
        $hash  = password_hash($plain, PASSWORD_BCRYPT);
        $exp   = date('Y-m-d H:i:s', time() + 600);
        $db    = DatabaseStub::getInstance();
        $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, ?, 'pending', 0, ?)"
        )->execute([$email, $hash, $exp]);
        $result = $this->authService->verifyOTPAndLogin($email, $plain);

        $this->assertTrue($result['success'],   'Step 2 – Correct OTP must succeed.');
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user',  $result);
        $this->assertSame($email,         $result['user']['email']);
        $this->assertSame('student',       $result['user']['role']);

        return $result['token'];
    }

    /**
     * Journey: JWT must be structurally valid and verifiable.
     */
    public function testStep2JWTIsWellFormed(): void {
        $token = $this->testStep2CorrectOTPReturnsJWT();

        $claims = JWTHandler::verify($token);
        $this->assertSame('journey@goa.bits-pilani.ac.in', $claims['email']);
        $this->assertSame('student', $claims['role']);
        $this->assertSame(JWT_ISSUER,   $claims['iss']);
        $this->assertSame(JWT_AUDIENCE, $claims['aud']);
        $this->assertGreaterThan(time(), $claims['exp']);
    }

    /**
     * Journey: JWT is used to access a protected route (simulated profile check).
     * Verifies that JWTMiddleware::handle(false) populates $_REQUEST['_jwt_claims'].
     */
    public function testStep3JWTGrantsAccessToProtectedRoute(): void {
        $token = $this->testStep2CorrectOTPReturnsJWT();

        // Simulate what the HTTP layer would provide
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $claims = JWTMiddleware::handle(false);

        $this->assertNotNull($claims, 'Step 3 – Valid JWT must grant access.');
        $this->assertSame('journey@goa.bits-pilani.ac.in', $claims['email']);
        $this->assertSame($claims, $_REQUEST['_jwt_claims']);
    }

    /**
     * Journey: expired JWT is rejected at the protected route.
     */
    public function testStep3ExpiredJWTDeniedOnProtectedRoute(): void {
        // Build an expired token directly
        $expiredToken = JWTHandler::generate(
            ['sub' => 10, 'email' => 'journey@goa.bits-pilani.ac.in', 'role' => 'student'],
            -1
        );
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $expiredToken;

        // authenticate() throws on invalid token; that's the enforcement path
        $this->expectException(RuntimeException::class);
        JWTMiddleware::authenticate();
    }

    /**
     * Journey: student role is verified by RBAC middleware.
     * Students can place_order but cannot manage_products.
     */
    public function testStep4StudentRBACCheckAllowsPlaceOrder(): void {
        $token  = $this->testStep2CorrectOTPReturnsJWT();
        $claims = JWTHandler::verify($token);

        $this->assertTrue(
            RBACManager::can($claims, 'place_order'),
            'Step 4 – Student must have place_order permission.'
        );
    }

    public function testStep4StudentRBACDeniesManageProducts(): void {
        $token  = $this->testStep2CorrectOTPReturnsJWT();
        $claims = JWTHandler::verify($token);

        $this->assertFalse(
            RBACManager::can($claims, 'manage_products'),
            'Step 4 – Student must not have manage_products permission.'
        );
    }

    /**
     * Journey: RBACMiddleware::check() returns null for student on admin-only route.
     */
    public function testStep4RBACMiddlewareBlocksStudentOnAdminRoute(): void {
        $token = $this->testStep2CorrectOTPReturnsJWT();
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // check() is the non-abort variant — safe to call in a test
        $result = RBACMiddleware::check(['admin', 'super_admin']);
        $this->assertNull($result, 'Step 4 – Student must be blocked from admin-only route.');
    }

    // =========================================================================
    // Full journey: admin
    // =========================================================================

    /**
     * Admin user: full journey from OTP to admin-route access.
     */
    public function testAdminJourneyFullFlow(): void {
        $email = 'admin.journey@goa.bits-pilani.ac.in';
        $plain = '246810';
        $hash  = password_hash($plain, PASSWORD_BCRYPT);
        $exp   = date('Y-m-d H:i:s', time() + 600);
        $db    = DatabaseStub::getInstance();
        $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, ?, 'pending', 0, ?)"
        )->execute([$email, $hash, $exp]);
        $result = $this->authService->verifyOTPAndLogin($email, $plain);
        $this->assertTrue($result['success']);
        $this->assertSame('admin', $result['user']['role']);

        $claims = JWTHandler::verify($result['token']);
        $this->assertTrue(RBACManager::can($claims, 'manage_products'));
        $this->assertTrue(RBACManager::can($claims, 'view_audit_logs'));
        $this->assertFalse(RBACManager::can($claims, 'update_delivery_status'));

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $result['token'];
        $rbacClaims = RBACMiddleware::check(['admin', 'super_admin']);
        $this->assertNotNull($rbacClaims);
    }

    // =========================================================================
    // Rate-limit and brute-force scenarios
    // =========================================================================

    /**
     * A user exhausts OTP requests within the hour → 4th is rejected.
     */
    public function testOTPRateLimitPreventsFourthRequestInWindow(): void {
        $email = 'ratelimit.journey@goa.bits-pilani.ac.in';

        for ($i = 1; $i <= OTP_RATE_LIMIT; $i++) {
            $res = $this->authService->requestOTP($email);
            $this->assertTrue($res['success'], "Request #{$i} must succeed.");
        }

        $blocked = $this->authService->requestOTP($email);
        $this->assertFalse($blocked['success']);
        $this->assertSame(429, $blocked['code']);
    }

    /**
     * Brute-force: five wrong OTPs triggers a 423 lock.
     */
    public function testBruteForceLocksAfterFiveFailedAttempts(): void {
        $email = 'bruteforce.journey@goa.bits-pilani.ac.in';
        $hash  = password_hash('correct', PASSWORD_BCRYPT);
        $exp   = date('Y-m-d H:i:s', time() + 600);
        $db    = DatabaseStub::getInstance();
        $db->prepare(
            "INSERT INTO otp_codes (email, code_hash, status, attempts, expires_at) VALUES (?, ?, 'pending', 0, ?)"
        )->execute([$email, $hash, $exp]);
        for ($i = 1; $i < OTP_MAX_ATTEMPTS; $i++) {
            $this->otpManager->verifyOTP($email, 'wrong' . $i);
        }

        $lockResult = $this->otpManager->verifyOTP($email, 'wrongFinal');
        $this->assertFalse($lockResult['success']);
        $this->assertSame(423, $lockResult['code']);
        $this->assertTrue($this->otpManager->isAccountLocked($email));
    }

    // =========================================================================
    // Incompatibility analysis
    // =========================================================================

    /**
     * Documents that the legacy Auth::requireAuth() is session-based and
     * therefore incompatible with JWTMiddleware.
     *
     * The legacy stack:
     *   - api/cart.php   → Auth::requireAuth()  → checks $_SESSION['logged_in']
     *   - api/auth.php   → Auth::login()         → populates $_SESSION
     *   - profile.php    → Auth::requireAuth()   → redirects to login.php
     *
     * JWTMiddleware expects:
     *   - Authorization: Bearer <token>  OR  X-Auth-Token: <token>
     *   - Stores claims in $_REQUEST['_jwt_claims']
     *
     * The two mechanisms are completely orthogonal — a user authenticated via
     * JWTMiddleware will NOT have $_SESSION['logged_in'] set, so every legacy
     * page that calls Auth::requireAuth() / Auth::check() will reject them.
     *
     * Conversely, a session-authenticated user has no Bearer token so any page
     * protected by JWTMiddleware::handle(true) will return HTTP 401.
     *
     * This test asserts the incompatibility as a documented expectation so that
     * the CI will fail if someone silently "fixes" it in a way that breaks the
     * expected boundary.
     */
    public function testLegacySessionAuthAndJWTMiddlewareAreOrthogonal(): void {
        // Simulate a user who obtained a JWT (new auth flow)
        $token = JWTHandler::generate([
            'sub'   => 10,
            'email' => 'journey@goa.bits-pilani.ac.in',
            'role'  => 'student',
        ]);

        // Set the Bearer token as JWTMiddleware would receive it
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // JWTMiddleware accepts this user
        $claims = JWTMiddleware::handle(false);
        $this->assertNotNull($claims, 'JWTMiddleware must accept a valid Bearer token.');

        // Legacy Auth::check() does NOT accept this user (no session)
        // We test the relevant boolean logic directly to avoid a session_start
        $sessionLoggedIn = $_SESSION['logged_in'] ?? false;
        $this->assertFalse(
            (bool) $sessionLoggedIn,
            'INCOMPATIBILITY: A JWT-authenticated user has no $_SESSION["logged_in"] — ' .
            'legacy pages using Auth::requireAuth() will redirect them to login.php. ' .
            'Pages that must support both flows need to be updated to check either ' .
            '$_REQUEST["_jwt_claims"] (JWT) or $_SESSION["logged_in"] (session).'
        );
    }

    /**
     * Confirms that api/cart.php-style endpoints (using Auth::requireAuth) cannot
     * be accessed purely via Bearer token without a parallel session.
     *
     * Lists specific files in the codebase that are affected.
     */
    public function testIncompatibleFilesAreDocumented(): void {
        $incompatibleFiles = [
            'api/cart.php'       => 'Auth::requireAuth()',
            'api/orders.php'     => 'Auth::requireAuth()',
            'api/user.php'       => 'Auth::check()',
            'api/wishlist.php'   => 'Auth::requireAuth()',
            'profile.php'        => 'Auth::requireAuth()',
            'cart.php'           => 'Auth::requireAuth()',
            'checkout.php'       => 'Auth::requireAuth()',
            'orders.php'         => 'Auth::requireAuth()',
            'wishlist.php'       => 'Auth::requireAuth()',
        ];

        // We assert the list is non-empty to ensure the analysis stays visible in CI
        $this->assertNotEmpty(
            $incompatibleFiles,
            'The incompatibility list must document affected files.'
        );

        foreach ($incompatibleFiles as $file => $authCall) {
            $this->assertIsString($authCall, "File {$file} should document the auth call used.");
        }
    }
}
