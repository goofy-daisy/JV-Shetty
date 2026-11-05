<?php
/**
 * EmailServiceStub
 *
 * Silent no-op email service for unit and integration tests.
 * Prevents any real SMTP connections from being opened during testing.
 *
 * Usage: require_once __DIR__ . '/EmailServiceStub.php';
 * (Must be required BEFORE AuthService is loaded for the first time.)
 */
if (!class_exists('EmailService', false)) {
    class EmailService {
        private static ?self $instance = null;

        private function __construct() {}

        public static function getInstance(): self {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /** Swallow all OTP email delivery silently during tests. */
        public function sendOTPEmail(string $email, string $otp): void {}

        /** Swallow all password-reset emails silently during tests. */
        public function sendPasswordResetEmail(string $email, string $token): void {}

        /** Swallow all verification emails silently during tests. */
        public function sendEmailVerification(string $email, string $name, string $token): void {}
    }
}
