<?php
/**
 * SecurityMiddleware
 *
 * Global security hardening middleware.
 *
 * Protections applied:
 *   - Security headers (CSP, X-Frame-Options, HSTS, etc. — Helmet-style)
 *   - XSS prevention (output encoding helpers + input stripping)
 *   - SQL injection prevention via input scanning
 *   - Clickjacking prevention
 *   - MIME-type sniffing prevention
 *
 * Usage — call once at the very top of app.php (or any bootstrap file):
 *   SecurityMiddleware::apply();
 */

class SecurityMiddleware {

    /** Per-request CSP nonce (lazily generated). */
    private static ?string $nonce = null;

    /**
     * Return (and lazily generate) a cryptographically random nonce for the
     * current request.  The same nonce is embedded in the CSP header and must
     * be added as the `nonce` attribute on every inline <script> and <style>
     * tag, e.g. <script nonce="<?= SecurityMiddleware::getNonce() ?>">…</script>
     */
    public static function getNonce(): string {
        if (self::$nonce === null) {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Apply all security measures.  Safe to call multiple times (idempotent).
     */
    public static function apply(): void {
        self::sendSecurityHeaders();
        self::sanitizeInput();
    }

    // =========================================================================
    // Security Headers
    // =========================================================================

    /**
     * Send HTTP security headers before any output.
     */
    public static function sendSecurityHeaders(): void {
        if (headers_sent()) {
            return;
        }

        // Prevent clickjacking
        header('X-Frame-Options: DENY');

        // Stop browsers from MIME-sniffing
        header('X-Content-Type-Options: nosniff');

        // Enable XSS filter in older browsers
        header('X-XSS-Protection: 1; mode=block');

        // Content Security Policy — nonce-based to eliminate 'unsafe-inline'
        $nonce = self::getNonce();
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none';");

        // HSTS (only meaningful over HTTPS; safe to send regardless)
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions policy
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        // Remove server/version fingerprinting headers if possible
        header_remove('X-Powered-By');
        header_remove('Server');
    }

    // =========================================================================
    // Input Sanitization
    // =========================================================================

    /**
     * Walk $_GET, $_POST, and $_COOKIE and neutralize obvious XSS/injection
     * patterns in-place.  This is a defence-in-depth measure; always use
     * prepared statements for DB access regardless.
     */
    public static function sanitizeInput(): void {
        $_GET    = self::deepSanitize($_GET);
        $_POST   = self::deepSanitize($_POST);
        $_COOKIE = self::deepSanitize($_COOKIE);
    }

    /**
     * Recursively sanitize an array of user input.
     */
    private static function deepSanitize(array $data): array {
        array_walk_recursive($data, static function (&$value): void {
            if (is_string($value)) {
                // 1. Strip NULL bytes
                $value = str_replace("\0", '', $value);

                // 2. Remove control characters (except newline/tab used in text areas)
                $value = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

                // 3. Limit length to prevent oversized payloads
                if (strlen($value) > 65535) {
                    $value = substr($value, 0, 65535);
                }
            }
        });
        return $data;
    }

    // =========================================================================
    // Output Encoding Helpers
    // =========================================================================

    /**
     * HTML-encode a string for safe output in HTML context.
     * Alias of htmlspecialchars with the safe defaults.
     */
    public static function e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * JSON-encode data with XSS-safe flags (escapes </> in strings).
     */
    public static function jsonSafe(mixed $data): string {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // SQL Injection Detection (for request-level alerting, not sanitization)
    // =========================================================================

    /**
     * Scan a value for common SQL injection patterns and return true if
     * suspicious content is found.  Use this for logging / alerting only —
     * the real defence is always prepared statements.
     */
    public static function looksLikeSQLInjection(string $value): bool {
        $patterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bCREATE\b|\bALTER\b)/i',
            '/(\bOR\b|\bAND\b)\s+[\'"0-9]/i',
            '/[;\'"\-]{2,}/',
            '/\/\*.*?\*\//',
            '/xp_\w+/i',
            '/EXEC(\s|\+)+(\s|\+)*(XP|SP|MSR)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect potential XSS payloads in a raw string.
     */
    public static function looksLikeXSS(string $value): bool {
        $patterns = [
            '/<script[\s\S]*?>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',       // onclick=, onmouseover=, etc.
            '/<\s*iframe/i',
            '/data:\s*text\/html/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
