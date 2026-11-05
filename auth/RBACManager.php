<?php
/**
 * RBACManager
 *
 * Role-Based Access Control for the JV Shetty campus auth system.
 *
 * Supported roles (mirrors the `roles` table):
 *   student, faculty, admin, super_admin, delivery_partner, customer
 *
 * Usage:
 *   RBACManager::can($claims, 'manage_products')  → bool
 *   RBACManager::requireRole($claims, ['admin', 'super_admin'])
 */

class RBACManager {

    /**
     * Built-in permission map per role.
     * In production these can also be loaded from the `roles.permissions` JSON
     * column in the database via self::loadFromDatabase().
     */
    private static array $rolePermissions = [
        'super_admin' => ['*'],                             // wildcard = all
        'admin'       => [
            'view_users', 'manage_users',
            'view_products', 'manage_products',
            'view_orders', 'manage_orders',
            'view_reports', 'manage_settings',
            'view_audit_logs',
        ],
        'faculty'     => [
            'view_products',
            'place_order', 'view_own_orders',
            'manage_profile',
        ],
        'student'     => [
            'view_products',
            'place_order', 'view_own_orders',
            'manage_profile',
        ],
        'customer'    => [
            'view_products',
            'place_order', 'view_own_orders',
            'manage_profile',
        ],
        'delivery_partner' => [
            'view_assigned_orders',
            'update_delivery_status',
            'manage_profile',
        ],
    ];

    // =========================================================================
    // Permission Checks
    // =========================================================================

    /**
     * Check whether the decoded JWT claims grant the given permission.
     *
     * @param array  $claims     Decoded JWT payload (must contain 'role' key)
     * @param string $permission Permission slug to check
     */
    public static function can(array $claims, string $permission): bool {
        $role = $claims['role'] ?? '';
        return self::roleHasPermission($role, $permission);
    }

    /**
     * Check whether a role string directly grants a permission.
     */
    public static function roleHasPermission(string $role, string $permission): bool {
        $permissions = self::$rolePermissions[$role] ?? [];

        // Wildcard — super_admin can do everything
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * Return all permissions for a given role.
     */
    public static function getPermissions(string $role): array {
        return self::$rolePermissions[$role] ?? [];
    }

    // =========================================================================
    // Enforcement Helpers (terminate on failure)
    // =========================================================================

    /**
     * Abort with 403 unless the JWT claims grant the required permission.
     */
    public static function requirePermission(array $claims, string $permission): void {
        if (!self::can($claims, $permission)) {
            self::forbidden("You do not have permission to perform this action.");
        }
    }

    /**
     * Abort with 403 unless the JWT claims contain one of the allowed roles.
     *
     * @param array    $claims       Decoded JWT payload
     * @param string[] $allowedRoles List of role slugs that are permitted
     */
    public static function requireRole(array $claims, array $allowedRoles): void {
        $role = $claims['role'] ?? '';
        if (!in_array($role, $allowedRoles, true)) {
            self::forbidden("Access restricted. Required role(s): " . implode(', ', $allowedRoles) . ".");
        }
    }

    /**
     * Check whether the JWT claims contain one of the allowed roles (no abort).
     */
    public static function hasRole(array $claims, array $allowedRoles): bool {
        return in_array($claims['role'] ?? '', $allowedRoles, true);
    }

    // =========================================================================
    // Database Sync (optional, for runtime updates)
    // =========================================================================

    /**
     * Overlay role permissions from the DB `roles.permissions` JSON column.
     * Call this once at boot if you want DB-driven RBAC.
     *
     * Only permission slugs that already exist in the built-in permission map
     * are accepted.  This prevents a compromised `roles` table from injecting
     * arbitrary (or wildcard `*`) permissions into the running process.
     */
    public static function loadFromDatabase(): void {
        try {
            $db   = Database::getInstance();
            $stmt = $db->query("SELECT name, permissions FROM roles WHERE permissions IS NOT NULL");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $perms = json_decode($row['permissions'], true);
                if (is_array($perms)) {
                    $allowed  = self::knownPermissionSlugs();
                    $filtered = array_values(
                        array_filter($perms, static fn($p) => is_string($p) && in_array($p, $allowed, true))
                    );
                    self::$rolePermissions[$row['name']] = $filtered;
                }
            }
        } catch (Exception $e) {
            error_log('[RBACManager::loadFromDatabase] ' . $e->getMessage());
        }
    }

    /**
     * Return the flat list of all permission slugs defined in the built-in map.
     * The wildcard `*` is intentionally excluded so it cannot be granted via DB.
     */
    private static function knownPermissionSlugs(): array {
        $slugs = [];
        foreach (self::$rolePermissions as $perms) {
            foreach ($perms as $p) {
                if ($p !== '*') {
                    $slugs[] = $p;
                }
            }
        }
        return array_unique($slugs);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function forbidden(string $message): never {
        $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
              || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        http_response_code(403);
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message, 'code' => 403]);
        } else {
            echo "<h2>403 Forbidden</h2><p>{$message}</p>";
        }
        exit;
    }
}
