<?php

namespace VirPanel\Core\Auth;

/**
 * Permission System
 *
 * Role-based access control (RBAC)
 */
class Permission
{
    /**
     * Permission definitions
     *
     * @var array
     */
    protected static array $permissions = [
        'root' => [
            // Root has all permissions
            '*',
        ],
        'admin' => [
            // WHM Admin permissions
            'accounts.view',
            'accounts.create',
            'accounts.edit',
            'accounts.delete',
            'accounts.suspend',
            'accounts.unsuspend',
            'packages.view',
            'packages.create',
            'packages.edit',
            'packages.delete',
            'resellers.view',
            'resellers.create',
            'resellers.edit',
            'resellers.delete',
            'settings.view',
            'settings.edit',
            'modules.view',
            'modules.install',
            'modules.uninstall',
            'templates.view',
            'templates.install',
            'templates.uninstall',
            'backups.view',
            'backups.create',
            'backups.restore',
            'system.view',
            'system.restart',
            'logs.view',
        ],
        'reseller' => [
            // Reseller permissions
            'accounts.view',
            'accounts.create',
            'accounts.edit',
            'accounts.suspend',
            'accounts.unsuspend',
            'packages.view',
            'packages.create',
            'packages.edit',
            'domains.view',
            'domains.create',
            'domains.edit',
            'emails.view',
            'emails.create',
            'emails.edit',
            'databases.view',
            'databases.create',
            'databases.edit',
        ],
        'user' => [
            // cPanel user permissions
            'domains.view',
            'domains.create',
            'emails.view',
            'emails.create',
            'emails.edit',
            'emails.delete',
            'databases.view',
            'databases.create',
            'databases.edit',
            'databases.delete',
            'files.view',
            'files.upload',
            'files.edit',
            'files.delete',
            'ftp.view',
            'ftp.create',
            'ftp.edit',
            'ftp.delete',
            'ssl.view',
            'ssl.install',
            'backups.view',
            'backups.create',
            'backups.download',
            'cron.view',
            'cron.create',
            'cron.edit',
            'cron.delete',
        ],
    ];

    /**
     * Check if user has permission
     *
     * @param string $permission
     * @param User|null $user
     * @return bool
     */
    public static function has(string $permission, $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (!$user) {
            return false;
        }

        $role = $user->getRole();

        // Root has all permissions
        if ($role === 'root') {
            return true;
        }

        $rolePermissions = self::$permissions[$role] ?? [];

        // Check wildcard
        if (in_array('*', $rolePermissions)) {
            return true;
        }

        // Check exact permission
        if (in_array($permission, $rolePermissions)) {
            return true;
        }

        // Check wildcard patterns (e.g., 'accounts.*')
        foreach ($rolePermissions as $rolePermission) {
            if (str_ends_with($rolePermission, '.*')) {
                $prefix = substr($rolePermission, 0, -2);
                if (str_starts_with($permission, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions
     *
     * @param array $permissions
     * @param User|null $user
     * @return bool
     */
    public static function hasAny(array $permissions, $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (self::has($permission, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     *
     * @param array $permissions
     * @param User|null $user
     * @return bool
     */
    public static function hasAll(array $permissions, $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (!self::has($permission, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permissions for a role
     *
     * @param string $role
     * @return array
     */
    public static function getPermissions(string $role): array
    {
        return self::$permissions[$role] ?? [];
    }

    /**
     * Get all roles
     *
     * @return array
     */
    public static function getRoles(): array
    {
        return array_keys(self::$permissions);
    }

    /**
     * Check if role exists
     *
     * @param string $role
     * @return bool
     */
    public static function roleExists(string $role): bool
    {
        return isset(self::$permissions[$role]);
    }

    /**
     * Add custom permission to role
     *
     * @param string $role
     * @param string $permission
     * @return void
     */
    public static function addPermission(string $role, string $permission): void
    {
        if (!isset(self::$permissions[$role])) {
            self::$permissions[$role] = [];
        }

        if (!in_array($permission, self::$permissions[$role])) {
            self::$permissions[$role][] = $permission;
        }
    }

    /**
     * Remove permission from role
     *
     * @param string $role
     * @param string $permission
     * @return void
     */
    public static function removePermission(string $role, string $permission): void
    {
        if (!isset(self::$permissions[$role])) {
            return;
        }

        $key = array_search($permission, self::$permissions[$role]);

        if ($key !== false) {
            unset(self::$permissions[$role][$key]);
            self::$permissions[$role] = array_values(self::$permissions[$role]);
        }
    }

    /**
     * Check if user can perform action on resource
     *
     * @param string $action
     * @param string $resource
     * @param mixed $resourceId
     * @return bool
     */
    public static function can(string $action, string $resource, $resourceId = null): bool
    {
        $permission = "{$resource}.{$action}";

        if (!self::has($permission)) {
            return false;
        }

        // Additional ownership checks for specific resources
        if ($resourceId !== null) {
            return self::owns($resource, $resourceId);
        }

        return true;
    }

    /**
     * Check if user owns a resource
     *
     * @param string $resource
     * @param mixed $resourceId
     * @return bool
     */
    public static function owns(string $resource, $resourceId): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Root and admin can access everything
        if (in_array($user->getRole(), ['root', 'admin'])) {
            return true;
        }

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        // Check ownership based on resource type
        switch ($resource) {
            case 'account':
            case 'accounts':
                $result = $db->fetchOne(
                    "SELECT COUNT(*) FROM {$prefix}accounts WHERE id = ? AND (user_id = ? OR reseller_id = ?)",
                    [$resourceId, $user->getId(), $user->getId()]
                );
                return $result > 0;

            case 'domain':
            case 'domains':
                $result = $db->fetchOne(
                    "SELECT COUNT(*) FROM {$prefix}domains d
                     INNER JOIN {$prefix}accounts a ON d.account_id = a.id
                     WHERE d.id = ? AND (a.user_id = ? OR a.reseller_id = ?)",
                    [$resourceId, $user->getId(), $user->getId()]
                );
                return $result > 0;

            default:
                return false;
        }
    }
}
