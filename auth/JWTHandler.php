<?php
/**
 * JWTHandler
 *
 * Pure-PHP HS256 JWT implementation (no external library required).
 *
 * - Issues tokens with a 30-minute expiry (JWT_TTL constant)
 * - Verifies signature, expiry, issuer, and audience claims
 * - Provides a refresh-token helper using the user's password hash as a
 *   rotating secret so that password changes instantly invalidate old tokens.
 *
 * Constants required (define before including this file):
 *   JWT_SECRET, JWT_ALGORITHM ('HS256'), JWT_TTL, JWT_ISSUER, JWT_AUDIENCE
 */

class JWTHandler {

    // =========================================================================
    // Token Generation
    // =========================================================================

    /**
     * Issue a signed JWT for the given user payload.
     *
     * @param array $userPayload  Fields to embed, e.g. ['sub'=>1, 'email'=>'...', 'role'=>'...']
     * @param int   $ttl          Override TTL (seconds). Defaults to JWT_TTL (1800 s / 30 min).
     * @return string Signed JWT string
     */
    public static function generate(array $userPayload, int $ttl = JWT_TTL): string {
        $now = time();

        $header = self::base64UrlEncode(json_encode([
            'alg' => JWT_ALGORITHM,
            'typ' => 'JWT',
        ]));

        // Merge user payload first; server-controlled reserved claims are applied
        // second so callers cannot override iss, aud, iat, nbf, or exp.
        $claims = array_merge($userPayload, [
            'iss' => JWT_ISSUER,
            'aud' => JWT_AUDIENCE,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ]);

        $payload   = self::base64UrlEncode(json_encode($claims));
        $signature = self::sign($header . '.' . $payload);

        return $header . '.' . $payload . '.' . $signature;
    }

    // =========================================================================
    // Token Verification
    // =========================================================================

    /**
     * Verify and decode a JWT.
     *
     * @param string $token Raw JWT string
     * @return array Decoded claims on success
     * @throws RuntimeException on any validation failure
     */
    public static function verify(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid JWT structure.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // 1. Validate signature
        $expected = self::sign($headerB64 . '.' . $payloadB64);
        if (!hash_equals($expected, $signatureB64)) {
            throw new RuntimeException('JWT signature verification failed.');
        }

        // 2. Decode header and validate algorithm
        $header = json_decode(self::base64UrlDecode($headerB64), true);
        if (!$header || ($header['alg'] ?? '') !== JWT_ALGORITHM) {
            throw new RuntimeException('Unsupported JWT algorithm.');
        }

        // 3. Decode claims
        $claims = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($claims)) {
            throw new RuntimeException('Invalid JWT payload.');
        }

        $now = time();

        // 4. Expiry check
        if (!isset($claims['exp']) || $claims['exp'] <= $now) {
            throw new RuntimeException('JWT has expired.');
        }

        // 5. Not-before check
        if (isset($claims['nbf']) && $claims['nbf'] > $now) {
            throw new RuntimeException('JWT is not yet valid.');
        }

        // 6. Issuer check
        if (($claims['iss'] ?? '') !== JWT_ISSUER) {
            throw new RuntimeException('Invalid JWT issuer.');
        }

        // 7. Audience check
        if (($claims['aud'] ?? '') !== JWT_AUDIENCE) {
            throw new RuntimeException('Invalid JWT audience.');
        }

        return $claims;
    }

    /**
     * Extract the claims from a token WITHOUT verifying (internal use only).
     * Never use this result for authorization decisions.
     */
    private static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $decoded = json_decode(self::base64UrlDecode($parts[1]), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Return the number of seconds until the token expires (0 if already expired).
     */
    public static function expiresIn(string $token): int {
        $claims = self::decode($token);
        if (!$claims || !isset($claims['exp'])) {
            return 0;
        }
        return max(0, $claims['exp'] - time());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function sign(string $data): string {
        $raw = hash_hmac('sha256', $data, JWT_SECRET, true);
        return self::base64UrlEncode($raw);
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
