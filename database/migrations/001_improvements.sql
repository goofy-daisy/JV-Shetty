-- =====================================================
-- JV SHETTY - MIGRATION 001
-- Adds: password_reset_tokens, coupons, rate_limit_attempts
-- Fixes: CH hostel descriptions
-- =====================================================

-- Password Reset Tokens Table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,  -- SHA-256 hashed token
    expires_at  TIMESTAMP NOT NULL,
    used_at     TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user  (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons Table
CREATE TABLE IF NOT EXISTS coupons (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    code             VARCHAR(50) NOT NULL UNIQUE,
    type             ENUM('discount_percent', 'fixed_amount') NOT NULL DEFAULT 'discount_percent',
    value            DECIMAL(10,2) NOT NULL,           -- % or ₹ amount
    min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0, -- minimum cart subtotal
    max_discount     DECIMAL(10,2) NULL,               -- cap for percent discounts
    max_uses         INT NULL,                         -- NULL = unlimited
    used_count       INT NOT NULL DEFAULT 0,
    valid_from       TIMESTAMP NULL,
    expires_at       TIMESTAMP NULL,
    is_active        BOOLEAN NOT NULL DEFAULT TRUE,
    description      VARCHAR(255) NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code    (code),
    INDEX idx_active  (is_active),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limit Attempts Table
CREATE TABLE IF NOT EXISTS rate_limit_attempts (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    identifier  VARCHAR(255) NOT NULL,   -- key:ip_address
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fix CH hostel description copy-paste errors
UPDATE hostel_list SET description = 'CH 1' WHERE name = 'CH1' AND description = 'AH 1';
UPDATE hostel_list SET description = 'CH 2' WHERE name = 'CH2' AND description = 'AH 2';

-- Default sample coupons
INSERT IGNORE INTO coupons (code, type, value, min_order_amount, max_discount, max_uses, description) VALUES
('WELCOME10', 'discount_percent', 10.00, 100.00, 50.00, 1,    'Welcome discount — 10% off your first order'),
('FLAT20',    'fixed_amount',     20.00, 200.00, NULL,  100,   '₹20 off on orders above ₹200'),
('BITS50',    'discount_percent', 50.00, 500.00, 100.00, 50,   '50% off up to ₹100 on orders above ₹500');
