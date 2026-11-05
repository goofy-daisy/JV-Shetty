<?php
/**
 * JWTMiddleware
 *
 * Validates the Bearer JWT on every request that requires authentication.
 * On success, stores the decoded claims in $_REQUEST['_jwt_claims'] so that
 * downstream handlers (controllers, API endpoints) can access them without
 * re-verifying the token.
 *
 * Usage (at the top of a protected endpoint):
 *   JWTMiddleware::handle();
 *   $claims = $_REQUEST['_jwt_claims'];
 */

class JWTMiddleware {

    /**
     * Enforce JWT authentication.  Terminates the request with 401 on failure.
     *
     * @param bool $required  When false the middleware validates the token if
     *                        present but does NOT abort for anonymous requests.
     * @return array|null     Decoded JWT claims (or null if $required is false
     *                        and no token was supplied).
     */
    public static function handle(bool $required = true): ?array {
        $token = self::extractToken();

        if ($token === null) {
            if ($required) {
                self::unauthorized('No authentication token provided.');
            }
            return null;
        }

        try {
            $claims = JWTHandler::verify($token);
        } catch (RuntimeException $e) {
            self::unauthorized($e->getMessage());
        }

        // Store in request scope for downstream use
        $_REQUEST['_jwt_claims'] = $claims;

        return $claims;
    }

    /**
     * Same as handle() but returns the claims array instead of storing them,
     * which is more convenient for unit-testable code.
     *
     * @throws RuntimeException on invalid/missing token
     */
    public static function authenticate(): array {
        $token = self::extractToken();
        if ($token === null) {
            throw new RuntimeException('No authentication token provided.');
        }
        return JWTHandler::verify($token);
    }

    // =========================================================================
    // Token Extraction
    // =========================================================================

    /**
     * Extract the raw JWT from the request.
     * Checks (in order):
     *   1. Authorization: Bearer <token> header
     *   2. X-Auth-Token header
     *
     * The ?token= query-string parameter is intentionally not supported:
     * tokens in URLs appear in server access logs, browser history, and
     * Referer headers, creating an unnecessary leakage path.
     */
    private static function extractToken(): ?string {
        // 1. Authorization header
        $auth = $_SERVER['HTTP_AUTHORIZATION']
             ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
             ?? '';
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        // 2. Custom header
        if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            return trim($_SERVER['HTTP_X_AUTH_TOKEN']);
        }

        return null;
    }

    // =========================================================================
    // Error Responses
    // =========================================================================

    private static function unauthorized(string $message): never {
        http_response_code(401);
        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        header('WWW-Authenticate: Bearer realm="jv-shetty"');

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message, 'code' => 401]);
        } else {
            echo "<h2>401 Unauthorized</h2><p>{$message}</p>";
        }
        exit;
    }
}
