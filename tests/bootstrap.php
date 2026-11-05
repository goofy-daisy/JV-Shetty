<?php
/**
 * PHPUnit Bootstrap
 * Defines constants and loads auth classes without a real DB/Redis connection.
 */

// ---- App / JWT / Redis constants ----------------------------------------
if (!defined('APP_NAME'))      define('APP_NAME',      'JV Shetty Test');
if (!defined('CAMPUS_DOMAIN')) define('CAMPUS_DOMAIN', 'goa.bits-pilani.ac.in');

// JWT
if (!defined('JWT_SECRET'))    define('JWT_SECRET',    'test-secret-key-not-for-production');
if (!defined('JWT_ALGORITHM')) define('JWT_ALGORITHM', 'HS256');
if (!defined('JWT_TTL'))       define('JWT_TTL',        1800);
if (!defined('JWT_ISSUER'))    define('JWT_ISSUER',    'jv-shetty-auth');
if (!defined('JWT_AUDIENCE'))  define('JWT_AUDIENCE',  'jv-shetty-app');

// Redis / OTP
if (!defined('OTP_TTL'))          define('OTP_TTL',           600);
if (!defined('OTP_RATE_LIMIT'))   define('OTP_RATE_LIMIT',      3);
if (!defined('OTP_RATE_WINDOW'))  define('OTP_RATE_WINDOW',  3600);
if (!defined('OTP_MAX_ATTEMPTS')) define('OTP_MAX_ATTEMPTS',    5);
if (!defined('OTP_LOCK_DURATION'))define('OTP_LOCK_DURATION', 3600);

// ---- Database stub (must come before OTPManager) -------------------------
require_once __DIR__ . '/stubs/DatabaseStub.php';
DatabaseStub::setup();

// ---- Auth classes --------------------------------------------------------
require_once __DIR__ . '/../auth/RedisClientInterface.php';
require_once __DIR__ . '/../auth/JWTHandler.php';
require_once __DIR__ . '/../auth/RBACManager.php';
require_once __DIR__ . '/../auth/OTPManager.php';
