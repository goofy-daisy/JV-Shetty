<?php
/**
 * Authentication Class
 * Handles user authentication, registration, and session management
 */

class Auth {
    private static ?array $user = null;
    private static ?array $role = null;
    
    /**
     * Register a new user
     */
    public static function register(array $data): array {
        $db = Database::getInstance();
        
        // Validate campus email
        if (!self::isValidCampusEmail($data['email'])) {
            return ['success' => false, 'message' => 'Only BITS Goa campus emails are allowed (@goa.bits-pilani.ac.in)'];
        }
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        try {
            $db->beginTransaction();
            
            // Get customer role
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'customer'");
            $stmt->execute();
            $roleId = $stmt->fetchColumn();
            
            if (!$roleId) {
                throw new Exception('Customer role not found');
            }
            
            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_ARGON2ID);
            
            // Create user
            $stmt = $db->prepare("
                INSERT INTO users (email, password_hash, role_id, is_verified) 
                VALUES (?, ?, ?, FALSE)
            ");
            $stmt->execute([$data['email'], $passwordHash, $roleId]);
            $userId = $db->lastInsertId();
            
            // Create user profile
            $stmt = $db->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, phone) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, 
                $data['first_name'], 
                $data['last_name'], 
                $data['phone'] ?? null
            ]);
            
            $db->commit();
            
            // Log audit
            self::logAudit('user_registered', 'users', $userId);
            
            return ['success' => true, 'user_id' => $userId, 'message' => 'Registration successful'];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Registration failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Login user
     */
    public static function login(string $email, string $password): array {
        $db = Database::getInstance();
        
        // Check for brute force
        if (self::isLockedOut($email)) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }
        
        // Find user
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, r.permissions 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.deleted_at IS NULL
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            self::recordFailedAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check account status
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Account is disabled. Contact support.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login_at = NOW(), login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['permissions'] = json_decode($user['permissions'], true);
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        // Log audit
        self::logAudit('user_login', 'users', $user['id']);
        
        return ['success' => true, 'user' => $user, 'message' => 'Login successful'];
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            self::logAudit('user_logout', 'users', $_SESSION['user_id']);
        }
        
        session_destroy();
        self::$user = null;
        self::$role = null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function check(): bool {
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
                self::logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
        }
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        
        if (self::$user === null) {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT u.*, r.name as role_name, r.display_name as role_display,
                       p.first_name, p.last_name, p.phone, p.avatar_url
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                LEFT JOIN user_profiles p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            self::$user = $stmt->fetch() ?: null;
        }
        
        return self::$user;
    }
    
    /**
     * Check if user has permission
     */
    public static function hasPermission(string $permission): bool {
        if (!self::check()) {
            return false;
        }
        
        $permissions = $_SESSION['permissions'] ?? [];
        
        // Owner has all permissions
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole(string $role): bool {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth(): void {
        if (!self::check()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
    
    /**
     * Require permission
     */
    public static function requirePermission(string $permission): void {
        self::requireAuth();
        
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            exit('Access denied');
        }
    }
    
    /**
     * Validate campus email
     */
    public static function isValidCampusEmail(string $email): bool {
        return str_ends_with(strtolower($email), '@goa.bits-pilani.ac.in');
    }
    
    /**
     * Check if account is locked out
     */
    private static function isLockedOut(string $email): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT login_attempts, locked_until FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return false;
        }
        
        if ($result['locked_until'] && strtotime($result['locked_until']) > time()) {
            return true;
        }
        
        return $result['login_attempts'] >= MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Record failed login attempt
     */
    private static function recordFailedAttempt(string $email): void {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1,
                locked_until = CASE WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? SECOND) ELSE NULL END
            WHERE email = ?
        ");
        $stmt->execute([MAX_LOGIN_ATTEMPTS, LOCKOUT_TIME, $email]);
    }
    
    /**
     * Log audit action
     */
    private static function logAudit(string $action, string $entityType, ?int $entityId = null): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $entityType,
                $entityId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Failed to log audit: " . $e->getMessage());
        }
    }

    // =====================================================================
    // PASSWORD RESET
    // =====================================================================

    /**
     * Request a password reset link. Always returns success to avoid user enumeration.
     */
    public static function requestPasswordReset(string $email): array {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare("SELECT id, email FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Don't reveal whether the email exists
                return ['success' => true, 'message' => 'If that email is registered, a reset link has been sent.'];
            }

            // Generate a cryptographically secure token
            $rawToken    = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $rawToken);
            $expiresAt   = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $db->beginTransaction();

            // Invalidate any previous unused tokens for this user
            $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
               ->execute([$user['id']]);

            // Store new hashed token
            $db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
               ->execute([$user['id'], $hashedToken, $expiresAt]);

            $db->commit();

            // Send email
            $emailService = EmailService::getInstance();
            $emailService->sendPasswordResetEmail($user['email'], $rawToken);

            self::logAudit('password_reset_requested', 'users', $user['id']);
            return ['success' => true, 'message' => 'If that email is registered, a reset link has been sent.'];

        } catch (Exception $e) {
            error_log('[Auth::requestPasswordReset] ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }

    /**
     * Reset a user's password using a valid, unexpired token.
     */
    public static function resetPassword(string $rawToken, string $newPassword): array {
        try {
            $db          = Database::getInstance();
            $hashedToken = hash('sha256', $rawToken);

            $stmt = $db->prepare("
                SELECT prt.id, prt.user_id
                FROM   password_reset_tokens prt
                WHERE  prt.token      = ?
                  AND  prt.used_at    IS NULL
                  AND  prt.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$hashedToken]);
            $reset = $stmt->fetch();

            if (!$reset) {
                return ['success' => false, 'message' => 'This reset link is invalid or has expired.'];
            }

            $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);

            $db->beginTransaction();

            $db->prepare("UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE id = ?")
               ->execute([$passwordHash, $reset['user_id']]);

            $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?")
               ->execute([$reset['id']]);

            $db->commit();

            self::logAudit('password_reset_completed', 'users', $reset['user_id']);
            return ['success' => true, 'message' => 'Password has been reset. You can now log in.'];

        } catch (Exception $e) {
            error_log('[Auth::resetPassword] ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }

    // =====================================================================
    // EMAIL VERIFICATION
    // =====================================================================

    /**
     * Generate and send an email verification link for a user.
     * Stores the hashed token in users.remember_token (re-used field).
     */
    public static function sendVerificationEmail(int $userId): void {
        try {
            $db         = Database::getInstance();
            $rawToken   = bin2hex(random_bytes(32));
            $hashed     = hash('sha256', $rawToken);

            $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")
               ->execute([$hashed, $userId]);

            $stmt = $db->prepare("
                SELECT u.email, p.first_name
                FROM   users u
                LEFT JOIN user_profiles p ON p.user_id = u.id
                WHERE  u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user) {
                $emailService = EmailService::getInstance();
                $emailService->sendEmailVerification($user['email'], $user['first_name'] ?? 'Student', $rawToken);
            }
        } catch (Exception $e) {
            error_log('[Auth::sendVerificationEmail] ' . $e->getMessage());
        }
    }

    /**
     * Verify an email address using the raw token from the link.
     */
    public static function verifyEmail(string $rawToken): array {
        try {
            $db     = Database::getInstance();
            $hashed = hash('sha256', $rawToken);

            $stmt = $db->prepare("SELECT id FROM users WHERE remember_token = ? AND is_verified = 0 LIMIT 1");
            $stmt->execute([$hashed]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or already used verification link.'];
            }

            $db->prepare("UPDATE users SET is_verified = 1, email_verified_at = NOW(), remember_token = NULL WHERE id = ?")
               ->execute([$user['id']]);

            self::logAudit('email_verified', 'users', $user['id']);
            return ['success' => true, 'message' => 'Email verified successfully! You can now log in.'];

        } catch (Exception $e) {
            error_log('[Auth::verifyEmail] ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
}
