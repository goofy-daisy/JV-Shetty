<?php
/**
 * System Health Check
 * Returns JSON status for all major subsystems.
 * Access: GET /health.php
 * Auth: Publicly accessible (no sensitive data exposed)
 */

require_once __DIR__ . '/config/app.php';

header('Content-Type: application/json');

$checks = [];
$allOk  = true;

// 1. Database Connectivity
try {
    $db   = Database::getInstance();
    $stmt = $db->query("SELECT 1");
    $checks['database'] = ['status' => 'ok', 'message' => 'Connected'];
} catch (Exception $e) {
    $checks['database'] = ['status' => 'error', 'message' => 'Connection failed'];
    $allOk = false;
}

// 2. Required PHP Constants
$constants = [
    'APP_NAME', 'APP_VERSION', 'BASE_URL',
    'PAYMENT_GATEWAY', 'PAYMENT_GATEWAY_KEY', 'PAYMENT_WEBHOOK_SECRET',
    'SMTP_HOST', 'SMTP_FROM_EMAIL',
    'MAX_LOGIN_ATTEMPTS', 'LOCKOUT_TIME'
];
$missingConsts = [];
foreach ($constants as $c) {
    if (!defined($c)) $missingConsts[] = $c;
}
if (empty($missingConsts)) {
    $checks['constants'] = ['status' => 'ok', 'message' => 'All required constants defined'];
} else {
    $checks['constants'] = ['status' => 'error', 'message' => 'Missing: ' . implode(', ', $missingConsts)];
    $allOk = false;
}

// 3. PHP Version
$phpVersion = PHP_VERSION;
$checks['php_version'] = [
    'status'  => version_compare($phpVersion, '8.0.0', '>=') ? 'ok' : 'warning',
    'message' => 'PHP ' . $phpVersion . (version_compare($phpVersion, '8.0.0', '>=') ? '' : ' (upgrade recommended)')
];

// 4. PHP Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json'];
$missingExts = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) $missingExts[] = $ext;
}
if (empty($missingExts)) {
    $checks['extensions'] = ['status' => 'ok', 'message' => 'All required extensions loaded'];
} else {
    $checks['extensions'] = ['status' => 'error', 'message' => 'Missing: ' . implode(', ', $missingExts)];
    $allOk = false;
}

// 5. Filesystem — uploads directory writable
$uploadsDir = __DIR__ . '/uploads/products';
if (is_dir($uploadsDir) && is_writable($uploadsDir)) {
    $checks['filesystem'] = ['status' => 'ok', 'message' => 'uploads/products is writable'];
} else {
    $checks['filesystem'] = [
        'status'  => 'warning',
        'message' => 'uploads/products ' . (!is_dir($uploadsDir) ? 'does not exist' : 'is not writable')
    ];
}

// 6. Session
try {
    $sessionStatus = session_status();
    $checks['session'] = [
        'status'  => $sessionStatus === PHP_SESSION_ACTIVE ? 'ok' : 'warning',
        'message' => $sessionStatus === PHP_SESSION_ACTIVE ? 'Session active' : 'Session not started'
    ];
} catch (Exception $e) {
    $checks['session'] = ['status' => 'error', 'message' => $e->getMessage()];
    $allOk = false;
}

// 7. Timezone
$checks['timezone'] = [
    'status'  => 'ok',
    'message' => date_default_timezone_get() . ' — ' . date('Y-m-d H:i:s')
];

// 8. Store Hours Table
try {
    $stmt = $db->query("SELECT COUNT(*) FROM time_windows");
    $count = $stmt->fetchColumn();
    $checks['store_hours'] = ['status' => 'ok', 'message' => $count . ' time windows configured'];
} catch (Exception $e) {
    $checks['store_hours'] = ['status' => 'warning', 'message' => 'Could not query store hours'];
}

// 9. Payment Gateway configured?
$gatewayConfigured = !empty(PAYMENT_GATEWAY_KEY) && !empty(PAYMENT_GATEWAY_SECRET);
$checks['payment_gateway'] = [
    'status'  => $gatewayConfigured ? 'ok' : 'warning',
    'message' => PAYMENT_GATEWAY . ($gatewayConfigured ? ' configured' : ' — API keys not set (COD only)')
];

// Summary
$statusCode = $allOk ? 200 : 503;
http_response_code($statusCode);

echo json_encode([
    'status'    => $allOk ? 'healthy' : 'degraded',
    'app'       => APP_NAME . ' v' . (defined('APP_VERSION') ? APP_VERSION : '1.0'),
    'timestamp' => date('c'),
    'checks'    => $checks
], JSON_PRETTY_PRINT);
