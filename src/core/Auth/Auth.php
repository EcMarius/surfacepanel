<?php

namespace VirPanel\Core\Auth;

use VirPanel\Models\User;

/**
 * Authentication Service
 *
 * Handles user authentication and session management
 */
class Auth
{
    /**
     * Session key for user data
     */
    const SESSION_USER_KEY = 'virpanel_user';

    /**
     * Session key for authenticated flag
     */
    const SESSION_AUTH_KEY = 'virpanel_authenticated';

    /**
     * Session key for CSRF token
     */
    const SESSION_CSRF_KEY = 'virpanel_csrf_token';

    /**
     * Current authenticated user
     *
     * @var User|null
     */
    protected static ?User $user = null;

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public static function check(): bool
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        return isset($_SESSION[self::SESSION_AUTH_KEY]) && $_SESSION[self::SESSION_AUTH_KEY] === true;
    }

    /**
     * Check if user is a guest (not authenticated)
     *
     * @return bool
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * Attempt to authenticate user
     *
     * @param string $username
     * @param string $password
     * @param bool $remember
     * @return bool
     */
    public static function attempt(string $username, string $password, bool $remember = false): bool
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        // Find user by username or email
        $userData = $db->fetchAssociative(
            "SELECT * FROM {$prefix}users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );

        if (!$userData) {
            logger("Login attempt failed: User not found - {$username}");
            return false;
        }

        // Verify password
        if (!password_verify($password, $userData['password'])) {
            logger("Login attempt failed: Invalid password - {$username}");

            // Log failed attempt
            self::logFailedAttempt($userData['id']);

            return false;
        }

        // Check if account is locked
        if (self::isLocked($userData['id'])) {
            logger("Login attempt failed: Account locked - {$username}");
            return false;
        }

        // Create user object
        $user = self::createUserFromData($userData);

        // Login the user
        self::login($user, $remember);

        // Update last login
        $db->update($prefix . 'users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => self::getClientIp(),
        ], ['id' => $user->getId()]);

        logger("User logged in successfully: {$username}");

        return true;
    }

    /**
     * Login a user
     *
     * @param User $user
     * @param bool $remember
     * @return void
     */
    public static function login(User $user, bool $remember = false): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Set session data
        $_SESSION[self::SESSION_AUTH_KEY] = true;
        $_SESSION[self::SESSION_USER_KEY] = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'full_name' => $user->getFullName(),
        ];

        // Generate CSRF token
        $_SESSION[self::SESSION_CSRF_KEY] = bin2hex(random_bytes(32));

        self::$user = $user;

        // Set remember me cookie
        if ($remember) {
            self::setRememberCookie($user);
        }
    }

    /**
     * Logout the current user
     *
     * @return void
     */
    public static function logout(): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $username = $_SESSION[self::SESSION_USER_KEY]['username'] ?? 'unknown';

        // Clear session
        $_SESSION = [];

        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy remember me cookie
        if (isset($_COOKIE['virpanel_remember'])) {
            setcookie('virpanel_remember', '', time() - 3600, '/');
        }

        // Destroy session
        session_destroy();

        self::$user = null;

        logger("User logged out: {$username}");
    }

    /**
     * Get the authenticated user
     *
     * @return User|null
     */
    public static function user(): ?User
    {
        if (self::$user !== null) {
            return self::$user;
        }

        if (!self::check()) {
            return null;
        }

        $userData = $_SESSION[self::SESSION_USER_KEY] ?? null;

        if (!$userData) {
            return null;
        }

        // Load full user from database
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $fullUserData = $db->fetchAssociative(
            "SELECT * FROM {$prefix}users WHERE id = ?",
            [$userData['id']]
        );

        if (!$fullUserData) {
            self::logout();
            return null;
        }

        self::$user = self::createUserFromData($fullUserData);

        return self::$user;
    }

    /**
     * Get user ID
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        return self::user()?->getId();
    }

    /**
     * Check if user has a specific role
     *
     * @param string $role
     * @return bool
     */
    public static function hasRole(string $role): bool
    {
        $user = self::user();
        return $user && $user->getRole() === $role;
    }

    /**
     * Check if user is root
     *
     * @return bool
     */
    public static function isRoot(): bool
    {
        return self::hasRole('root');
    }

    /**
     * Check if user is admin
     *
     * @return bool
     */
    public static function isAdmin(): bool
    {
        return self::hasRole('admin') || self::isRoot();
    }

    /**
     * Check if user is reseller
     *
     * @return bool
     */
    public static function isReseller(): bool
    {
        return self::hasRole('reseller');
    }

    /**
     * Verify CSRF token
     *
     * @param string $token
     * @return bool
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_CSRF_KEY] ?? null;

        return $sessionToken && hash_equals($sessionToken, $token);
    }

    /**
     * Get CSRF token
     *
     * @return string
     */
    public static function getCsrfToken(): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_CSRF_KEY])) {
            $_SESSION[self::SESSION_CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_CSRF_KEY];
    }

    /**
     * Create user object from database data
     *
     * @param array $data
     * @return User
     */
    protected static function createUserFromData(array $data): User
    {
        $user = new User();

        // Use reflection to set protected properties
        $reflection = new \ReflectionClass($user);

        foreach ($data as $key => $value) {
            $property = self::snakeToCamel($key);

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);

                // Convert date strings to DateTime
                if (in_array($key, ['created_at', 'updated_at', 'last_login_at']) && $value) {
                    $value = new \DateTime($value);
                }

                // Convert boolean values
                if (in_array($key, ['is_active', 'two_factor_enabled'])) {
                    $value = (bool) $value;
                }

                $prop->setValue($user, $value);
            }
        }

        return $user;
    }

    /**
     * Convert snake_case to camelCase
     *
     * @param string $string
     * @return string
     */
    protected static function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    /**
     * Set remember me cookie
     *
     * @param User $user
     * @return void
     */
    protected static function setRememberCookie(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (86400 * 30); // 30 days

        // Store token in database (would need a remember_tokens table in production)
        // For now, just set a cookie with user ID (not secure, just for demo)
        setcookie('virpanel_remember', $token, $expiry, '/', '', true, true);
    }

    /**
     * Log failed login attempt
     *
     * @param int $userId
     * @return void
     */
    protected static function logFailedAttempt(int $userId): void
    {
        // Implementation would track failed attempts in database
        // and implement account locking after X failed attempts
    }

    /**
     * Check if account is locked
     *
     * @param int $userId
     * @return bool
     */
    protected static function isLocked(int $userId): bool
    {
        // Implementation would check if account is locked
        // due to too many failed login attempts
        return false;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    protected static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
}
