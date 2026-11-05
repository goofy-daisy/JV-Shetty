<?php
/**
 * Redis Configuration
 * Used for OTP rate-limiting (sliding window) and session caching.
 */

define('REDIS_HOST',    $_ENV['REDIS_HOST']    ?? '127.0.0.1');
define('REDIS_PORT',    (int) ($_ENV['REDIS_PORT']    ?? 6379));
define('REDIS_PASS',    $_ENV['REDIS_PASS']    ?? '');
define('REDIS_DB',      (int) ($_ENV['REDIS_DB']      ?? 0));
define('REDIS_TIMEOUT', 2.0);  // connection timeout in seconds

// OTP-specific rate-limiting constants
define('OTP_TTL',             600);  // OTP valid for 10 minutes (600 s)
define('OTP_RATE_LIMIT',        3);  // max OTP requests per sliding window
define('OTP_RATE_WINDOW',    3600);  // sliding window size in seconds (1 hour)
define('OTP_MAX_ATTEMPTS',      5);  // max failed verifications before lock
define('OTP_LOCK_DURATION',  3600);  // account lock duration in seconds (1 hour)
