<?php
/**
 * JV Shetty E-Commerce Application
 * Campus Supermarket for BITS Pilani Goa
 * 
 * @author Kushagra
 * @version 1.0.0
 */

define('APP_NAME', 'JV Shetty');
define('APP_VERSION', '1.0.0');
define('CAMPUS_DOMAIN', 'goa.bits-pilani.ac.in');
define('STORE_OPEN_HOUR', 16); // 4:00 PM
// Store closes at 9:00 PM (21:00)
define('STORE_CLOSE_HOUR', 21); // 9:00 PM

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'jv_shetty_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'jvuser');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'jvpass123');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost/jv_shetty');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('UPLOADS_PATH', __DIR__ . '/../uploads');

define('SESSION_LIFETIME', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('RATE_LIMIT_ENABLED', filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN));

define('PAYMENT_GATEWAY', $_ENV['PAYMENT_GATEWAY'] ?? 'razorpay'); // 'razorpay', 'stripe', 'paypal'
define('PAYMENT_GATEWAY_KEY', $_ENV['PAYMENT_GATEWAY_KEY'] ?? '');
define('PAYMENT_GATEWAY_SECRET', $_ENV['PAYMENT_GATEWAY_SECRET'] ?? '');
define('PAYMENT_WEBHOOK_SECRET', $_ENV['PAYMENT_WEBHOOK_SECRET'] ?? '');
define('PAYMENT_TEST_MODE', filter_var($_ENV['PAYMENT_TEST_MODE'] ?? 'true', FILTER_VALIDATE_BOOLEAN));

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? '');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@jvshetty.bitsgoa.ac.in');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'JV Shetty Store');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Load core classes
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/ErrorHandler.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/BaseModel.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/StoreHours.php';
require_once __DIR__ . '/../core/EmailService.php';
require_once __DIR__ . '/../core/PaymentGateway.php';
require_once __DIR__ . '/../core/RateLimit.php';

// Register global error/exception handler
ErrorHandler::register();

// Maintenance mode — check system_settings and bounce non-admins
(function () {
    // Skip for the maintenance page itself, health check, and API
    $skip = ['maintenance.php', 'health.php'];
    $file = basename($_SERVER['PHP_SELF'] ?? '');
    if (in_array($file, $skip, true))
        return;
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
        return;

    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        if ($val === 'true' || $val === '1') {
            // Allow admins through
            if (isset($_SESSION['user_id'])) {
                $roleStmt = $db->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
                $roleStmt->execute([$_SESSION['user_id']]);
                $role = $roleStmt->fetchColumn();
                if (in_array($role, ['admin', 'super_admin'], true))
                    return;
            }
            header('Location: ' . BASE_URL . '/maintenance.php');
            exit;
        }
    }
    catch (Exception $e) {
        // Fail open — if DB unreachable, don't block site
        error_log('[MaintenanceCheck] ' . $e->getMessage());
    }
})();