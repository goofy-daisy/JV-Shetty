<?php
/**
 * RBACMiddleware
 *
 * Integrates JWT verification with Role-Based Access Control.
 * Combines JWTMiddleware (authentication) with RBACManager (authorization).
 *
 * Usage:
 *   // Require any authenticated user
 *   RBACMiddleware::handle();
 *
 *   // Require a specific role
 *   RBACMiddleware::handle(['admin', 'super_admin']);
 *
 *   // Require a specific permission
 *   RBACMiddleware::requirePermission('manage_products');
 */

class RBACMiddleware {

    /**
     * Authenticate the request via JWT and, optionally, enforce role membership.
     *
     * @param string[] $allowedRoles  Empty array = any authenticated user is allowed.
     * @return array                  Decoded JWT claims
     */
    public static function handle(array $allowedRoles = []): array {
        // 1. Verify JWT
        $claims = JWTMiddleware::handle(true);

        // 2. Role check (if roles were specified)
        if (!empty($allowedRoles)) {
            RBACManager::requireRole($claims, $allowedRoles);
        }

        return $claims;
    }

    /**
     * Authenticate and require the caller to hold a specific permission.
     *
     * @param string $permission  Permission slug (e.g. 'manage_products')
     * @return array              Decoded JWT claims
     */
    public static function requirePermission(string $permission): array {
        $claims = JWTMiddleware::handle(true);
        RBACManager::requirePermission($claims, $permission);
        return $claims;
    }

    /**
     * Check whether the current JWT claims include a role, without aborting.
     * Returns null if the token is absent or invalid.
     *
     * @param string[] $allowedRoles
     */
    public static function check(array $allowedRoles = []): ?array {
        $claims = JWTMiddleware::handle(false);
        if ($claims === null) {
            return null;
        }
        if (!empty($allowedRoles) && !RBACManager::hasRole($claims, $allowedRoles)) {
            return null;
        }
        return $claims;
    }
}
