-- =====================================================
-- JV SHETTY - MIGRATION 002
-- OTP Authentication & RBAC Support
-- Adds: otp_codes table
-- Updates: users table (is_locked, locked_until already present)
-- =====================================================

-- OTP Codes Table
-- Stores one-time-passwords for campus-verified authentication.
-- code_hash is a bcrypt hash of the 6-digit OTP.
CREATE TABLE IF NOT EXISTS otp_codes (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    email       VARCHAR(255) NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,          -- bcrypt hash of OTP
    status      ENUM('pending','verified','expired','locked') NOT NULL DEFAULT 'pending',
    attempts    INT NOT NULL DEFAULT 0,          -- failed verification attempts
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP NOT NULL,              -- created_at + 600 seconds (10 min)
    verified_at TIMESTAMP NULL,
    INDEX idx_email      (email),
    INDEX idx_status     (status),
    INDEX idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure users table has the is_locked and locked_until columns
-- (login_attempts and locked_until already exist in schema.sql;
--  this migration adds nothing extra so it is idempotent)
