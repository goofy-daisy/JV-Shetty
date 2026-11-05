<?php
/**
 * JWT Configuration
 * Settings for JSON Web Token generation and validation.
 */

$_jwtSecret = $_ENV['JWT_SECRET'] ?? '';
if ($_jwtSecret === '') {
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        throw new RuntimeException('JWT_SECRET environment variable is required in production.');
    }
    $_jwtSecret = 'dev-only-insecure-secret';
}

define('JWT_SECRET',     $_jwtSecret);
define('JWT_ALGORITHM',  'HS256');
define('JWT_TTL',        1800);   // 30 minutes in seconds
define('JWT_ISSUER',     'jv-shetty-auth');
define('JWT_AUDIENCE',   'jv-shetty-app');

unset($_jwtSecret);
