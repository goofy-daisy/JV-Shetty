# OTP Authentication & RBAC System — Setup Guide

## Overview

This document describes the campus-verified OTP authentication and RBAC
authorization system added to JV Shetty (BITS Pilani Goa Campus Supermarket).

All auth code lives in **`/jv_shetty/auth/`**.

---

## Directory Structure

```
jv_shetty/
├── auth/
│   ├── RedisClientInterface.php   ← Interface for Redis (enables DI / testing)
│   ├── RedisFactory.php           ← Connects to Redis, returns RedisAdapter
│   ├── OTPManager.php             ← OTP lifecycle: generate / verify / rate-limit / lock
│   ├── JWTHandler.php             ← Pure-PHP HS256 JWT (no external library)
│   ├── RBACManager.php            ← Role-Based Access Control
│   ├── AuthService.php            ← High-level orchestration (OTP + JWT + Email)
│   └── middleware/
│       ├── JWTMiddleware.php      ← Verifies Bearer token, stores claims
│       ├── RBACMiddleware.php     ← Combines JWT auth + role/permission check
│       └── SecurityMiddleware.php ← Security headers, XSS/SQLi detection
├── config/
│   ├── app.php                    ← Existing app config (DB, sessions, etc.)
│   ├── jwt.php                    ← JWT_SECRET, JWT_TTL (1800 s / 30 min)
│   └── redis.php                  ← Redis connection + OTP constants
├── database/
│   └── migrations/
│       └── 002_otp_auth.sql       ← Creates otp_codes table
└── tests/
    ├── phpunit.xml
    ├── bootstrap.php
    ├── OTPBoundaryTest.php        ← 24 unit tests (all boundary conditions)
    └── stubs/
        ├── RedisStub.php          ← In-memory Redis for tests
        └── DatabaseStub.php       ← SQLite in-memory DB for tests
```

---

## Environment Variables

Add these to your `.env` file or server environment:

```dotenv
# JWT
JWT_SECRET=replace-with-a-long-random-string-at-least-32-chars

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASS=
REDIS_DB=0
```

---

## Database Migration

Run the migration after the existing schema:

```bash
mysql -u jvuser -p jv_shetty_db < jv_shetty/database/migrations/002_otp_auth.sql
```

---

## Loading the Auth System

Add to your bootstrap / `config/app.php` after the existing `require_once` lines:

```php
// Auth system
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../auth/RedisClientInterface.php';
require_once __DIR__ . '/../auth/RedisFactory.php';
require_once __DIR__ . '/../auth/OTPManager.php';
require_once __DIR__ . '/../auth/JWTHandler.php';
require_once __DIR__ . '/../auth/RBACManager.php';
require_once __DIR__ . '/../auth/AuthService.php';
require_once __DIR__ . '/../auth/middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../auth/middleware/JWTMiddleware.php';
require_once __DIR__ . '/../auth/middleware/RBACMiddleware.php';

// Apply security headers on every request
SecurityMiddleware::apply();
```

---

## Usage Examples

### Request an OTP

```php
$redis      = RedisFactory::connect();
$otpManager = new OTPManager($redis);
$authService = new AuthService($otpManager);

$result = $authService->requestOTP('f20230001@goa.bits-pilani.ac.in');
// $result = ['success' => true, 'message' => 'OTP sent to your email address.']
// Rate limited → ['success' => false, 'code' => 429, ...]
```

### Verify OTP & Get JWT

```php
$result = $authService->verifyOTPAndLogin('f20230001@goa.bits-pilani.ac.in', '482931');
// Success → ['success' => true, 'token' => 'eyJ...', 'user' => [...]]
// Failure → ['success' => false, 'message' => 'Invalid OTP. 3 attempt(s) remaining.']
```

### Protect an API Endpoint (JWT + RBAC)

```php
// Any authenticated user
$claims = JWTMiddleware::handle();

// Admin or super_admin only
$claims = RBACMiddleware::handle(['admin', 'super_admin']);

// Specific permission
$claims = RBACMiddleware::requirePermission('manage_products');

// Access user info from claims
echo $claims['email'];   // f20230001@goa.bits-pilani.ac.in
echo $claims['role'];    // student | faculty | admin | ...
```

---

## Security Constraints

| Constraint | Value |
|---|---|
| OTP TTL | **10 minutes** (600 s) |
| OTP rate limit | **3 requests per hour** per email (sliding window, Redis) |
| Brute-force lockout | **5 failed verifications** → locked for **1 hour** |
| JWT expiry | **30 minutes** (1800 s) |
| JWT algorithm | HS256 |
| OTP hashing | bcrypt (PASSWORD_BCRYPT) |
| Campus domain | `@goa.bits-pilani.ac.in` |

---

## Running Tests

```bash
cd jv_shetty/tests
phpunit --configuration phpunit.xml
```

Expected output: **24 tests, 39 assertions** — all passing.

Tests cover:
- OTP valid at 9 min 59 s (pass) vs 10 min 01 s (fail)
- 4th OTP request within the hour (429-equivalent)
- 5th failed attempt triggers account lock
- JWT valid before expiry, fails after
- JWT tampered signature/payload detection
- RBAC permission checks per role
- Security middleware (XSS/SQLi detection, output encoding)
