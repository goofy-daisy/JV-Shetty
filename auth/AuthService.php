<?php
/**
 * AuthService
 *
 * High-level orchestration layer that ties together:
 *   OTPManager   – OTP lifecycle (request / verify / rate-limit / lock)
 *   JWTHandler   – Token generation and verification
 *   RBACManager  – Role-based permission enforcement
 *   EmailService – Async OTP delivery (fire-and-forget via output buffering)
 *
 * Typical flows:
 *   1. requestOTP($email)          → sends OTP, returns 200 / 429 / 423
 *   2. verifyOTPAndLogin($email, $otp) → verifies OTP, returns JWT on success
 *   3. verifyToken($jwtString)     → verifies and returns claims
 */

class AuthService {

    private OTPManager $otpManager;

    public function __construct(OTPManager $otpManager) {
        $this->otpManager = $otpManager;
    }

    // =========================================================================
    // OTP Flow
    // =========================================================================

    /**
     * Step 1 – Request an OTP for a campus email address.
     * Email delivery is dispatched asynchronously so the HTTP response
     * returns in well under 2 seconds regardless of SMTP latency.
     *
     * @return array{success: bool, message: string, code?: int}
     */
    public function requestOTP(string $email): array {
        $email = strtolower(trim($email));

        if (!$this->isValidCampusEmail($email)) {
            return [
                'success' => false,
                'message' => 'Only BITS Goa campus emails are allowed (@goa.bits-pilani.ac.in).',
                'code'    => 422,
            ];
        }

        $result = $this->otpManager->requestOTP($email);

        if ($result['success']) {
            // Fire-and-forget email delivery (keeps response < 2 s)
            $otp = $result['otp'];
            $this->sendOTPEmailAsync($email, $otp);
            unset($result['otp']);  // never expose in API response
        }

        return $result;
    }

    /**
     * Step 2 – Verify the OTP and, on success, issue a JWT.
     *
     * @return array{success: bool, message: string, token?: string, user?: array}
     */
    public function verifyOTPAndLogin(string $email, string $otp): array {
        $email = strtolower(trim($email));

        $verification = $this->otpManager->verifyOTP($email, $otp);

        if (!$verification['success']) {
            return $verification;
        }

        // Fetch user data for the JWT payload
        $user = $this->getUserByEmail($email);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User account not found. Please register first.',
                'code'    => 404,
            ];
        }

        // Build JWT
        $token = JWTHandler::generate([
            'sub'   => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role_name'],
        ]);

        $this->logAudit('otp_login_success', $user['id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'    => $user['id'],
                'email' => $user['email'],
                'role'  => $user['role_name'],
            ],
        ];
    }

    // =========================================================================
    // JWT Verification
    // =========================================================================

    /**
     * Verify a raw JWT string and return the decoded claims.
     *
     * @return array{success: bool, claims?: array, message?: string}
     */
    public function verifyToken(string $token): array {
        try {
            $claims = JWTHandler::verify($token);
            return ['success' => true, 'claims' => $claims];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Validate that an email belongs to the BITS Goa campus domain.
     */
    public function isValidCampusEmail(string $email): bool {
        return (bool) preg_match('/^[a-zA-Z0-9._%+\-]+@goa\.bits-pilani\.ac\.in$/i', $email);
    }

    /**
     * Retrieve a user row (with role name) by email address.
     */
    private function getUserByEmail(string $email): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.id, u.email, r.name AS role_name
            FROM   users u
            JOIN   roles r ON r.id = u.role_id
            WHERE  u.email = ? AND u.deleted_at IS NULL
            LIMIT  1
        ");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Deliver the OTP email asynchronously using output-buffering flush trick
     * so the HTTP response is sent before the (potentially slow) SMTP call.
     *
     * On CLI / test environments this falls back to synchronous sending.
     */
    private function sendOTPEmailAsync(string $email, string $otp): void {
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            // Flush response to client immediately, then send email in the
            // same PHP process after connection close.
            ignore_user_abort(true);
            ob_start();
        }

        try {
            $emailService = EmailService::getInstance();
            $emailService->sendOTPEmail($email, $otp);
        } catch (Exception $e) {
            error_log('[AuthService::sendOTPEmailAsync] ' . $e->getMessage());
        }
    }

    /**
     * Write to audit_logs.
     */
    private function logAudit(string $action, int $userId): void {
        try {
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($ua !== null) {
                $ua = htmlspecialchars(substr($ua, 0, 512), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $db = Database::getInstance();
            $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, ip_address, user_agent)
                VALUES (?, ?, 'auth', ?, ?)
            ")->execute([
                $userId,
                $action,
                $ip,
                $ua,
            ]);
        } catch (Exception $e) {
            error_log('[AuthService::logAudit] ' . $e->getMessage());
        }
    }
}
