<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class DatabaseController extends Controller
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->template = new TemplateEngine($app);
        $this->prefix = config('database.prefix', 'vp_');
    }

    /**
     * Display database management overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get account info for username prefix
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        // Get databases
        $databases = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}mysql_databases WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Get database users
        $dbUsers = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}mysql_users WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Get user-database mappings
        $userDatabases = $this->db->fetchAllAssociative(
            "SELECT ud.*, u.username as db_username, d.database_name
             FROM {$this->prefix}mysql_user_databases ud
             JOIN {$this->prefix}mysql_users u ON ud.user_id = u.id
             JOIN {$this->prefix}mysql_databases d ON ud.database_id = d.id
             WHERE ud.account_id = ?
             ORDER BY ud.created_at DESC",
            [$accountId]
        );

        return new Response($this->template->render('user/databases/index.html.twig', [
            'databases' => $databases,
            'dbUsers' => $dbUsers,
            'userDatabases' => $userDatabases,
            'accountUsername' => $account['username'],
        ]));
    }

    /**
     * Create database
     */
    public function storeDatabase(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $dbName = strtolower(trim($request->request->get('database_name')));

        // Get account username for prefix
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        $username = $account['username'];
        $fullDbName = "{$username}_{$dbName}";

        // Validation
        if (!preg_match('/^[a-z0-9_]+$/', $dbName)) {
            $_SESSION['error'] = 'Database name can only contain lowercase letters, numbers and underscores';
            return new RedirectResponse('/user/databases');
        }

        if (strlen($fullDbName) > 64) {
            $_SESSION['error'] = 'Database name is too long (max 64 characters including prefix)';
            return new RedirectResponse('/user/databases');
        }

        // Check if database already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}mysql_databases WHERE database_name = ?",
            [$fullDbName]
        );

        if ($exists) {
            $_SESSION['error'] = 'Database already exists';
            return new RedirectResponse('/user/databases');
        }

        try {
            $this->db->beginTransaction();

            // Insert into VirPanel database
            $this->db->insert($this->prefix . 'mysql_databases', [
                'account_id' => $accountId,
                'database_name' => $fullDbName,
                'size' => 0,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create actual MySQL database
            $this->createMySQLDatabase($fullDbName);

            $this->db->commit();

            $_SESSION['success'] = "Database '{$fullDbName}' created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create database: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Delete database
     */
    public function deleteDatabase(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $database = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}mysql_databases WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$database) {
                $_SESSION['error'] = 'Database not found';
                return new RedirectResponse('/user/databases');
            }

            $this->db->beginTransaction();

            // Remove user-database associations
            $this->db->delete($this->prefix . 'mysql_user_databases', [
                'database_id' => $id,
            ]);

            // Delete from VirPanel
            $this->db->delete($this->prefix . 'mysql_databases', ['id' => $id]);

            // Drop actual MySQL database
            $this->dropMySQLDatabase($database['database_name']);

            $this->db->commit();

            $_SESSION['success'] = "Database '{$database['database_name']}' deleted successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete database: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Create database user
     */
    public function storeUser(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $userName = strtolower(trim($request->request->get('username')));
        $password = $request->request->get('password');
        $host = $request->request->get('host', 'localhost');

        // Get account username for prefix
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        $accountUsername = $account['username'];
        $fullUserName = "{$accountUsername}_{$userName}";

        // Validation
        if (!preg_match('/^[a-z0-9_]+$/', $userName)) {
            $_SESSION['error'] = 'Username can only contain lowercase letters, numbers and underscores';
            return new RedirectResponse('/user/databases');
        }

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters';
            return new RedirectResponse('/user/databases');
        }

        if (strlen($fullUserName) > 32) {
            $_SESSION['error'] = 'Username is too long (max 32 characters including prefix)';
            return new RedirectResponse('/user/databases');
        }

        // Check if user already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}mysql_users WHERE username = ? AND host = ?",
            [$fullUserName, $host]
        );

        if ($exists) {
            $_SESSION['error'] = 'Database user already exists';
            return new RedirectResponse('/user/databases');
        }

        try {
            $this->db->beginTransaction();

            // Insert into VirPanel database
            $this->db->insert($this->prefix . 'mysql_users', [
                'account_id' => $accountId,
                'username' => $fullUserName,
                'host' => $host,
                'password' => password_hash($password, PASSWORD_ARGON2ID),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create actual MySQL user
            $this->createMySQLUser($fullUserName, $password, $host);

            $this->db->commit();

            $_SESSION['success'] = "Database user '{$fullUserName}' created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create user: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Update user password
     */
    public function updateUserPassword(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();
        $password = $request->request->get('password');

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters';
            return new RedirectResponse('/user/databases');
        }

        try {
            $user = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}mysql_users WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$user) {
                $_SESSION['error'] = 'User not found';
                return new RedirectResponse('/user/databases');
            }

            $this->db->beginTransaction();

            // Update VirPanel database
            $this->db->update($this->prefix . 'mysql_users', [
                'password' => password_hash($password, PASSWORD_ARGON2ID),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // Update MySQL user password
            $this->updateMySQLUserPassword($user['username'], $password, $user['host']);

            $this->db->commit();

            $_SESSION['success'] = 'User password updated successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to update password: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Delete database user
     */
    public function deleteUser(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $user = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}mysql_users WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$user) {
                $_SESSION['error'] = 'User not found';
                return new RedirectResponse('/user/databases');
            }

            $this->db->beginTransaction();

            // Remove user-database associations
            $this->db->delete($this->prefix . 'mysql_user_databases', [
                'user_id' => $id,
            ]);

            // Delete from VirPanel
            $this->db->delete($this->prefix . 'mysql_users', ['id' => $id]);

            // Drop actual MySQL user
            $this->dropMySQLUser($user['username'], $user['host']);

            $this->db->commit();

            $_SESSION['success'] = "User '{$user['username']}' deleted successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete user: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Add user to database with privileges
     */
    public function addUserToDatabase(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $userId = (int)$request->request->get('user_id');
        $databaseId = (int)$request->request->get('database_id');
        $privileges = $request->request->get('privileges', []);

        try {
            // Verify user and database belong to this account
            $user = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}mysql_users WHERE id = ? AND account_id = ?",
                [$userId, $accountId]
            );

            $database = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}mysql_databases WHERE id = ? AND account_id = ?",
                [$databaseId, $accountId]
            );

            if (!$user || !$database) {
                $_SESSION['error'] = 'User or database not found';
                return new RedirectResponse('/user/databases');
            }

            // Check if mapping already exists
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}mysql_user_databases WHERE user_id = ? AND database_id = ?",
                [$userId, $databaseId]
            );

            if ($exists) {
                $_SESSION['error'] = 'User already has access to this database';
                return new RedirectResponse('/user/databases');
            }

            $this->db->beginTransaction();

            // Insert mapping
            $this->db->insert($this->prefix . 'mysql_user_databases', [
                'account_id' => $accountId,
                'user_id' => $userId,
                'database_id' => $databaseId,
                'privileges' => json_encode($privileges),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Grant MySQL privileges
            $this->grantMySQLPrivileges($user['username'], $database['database_name'], $user['host'], $privileges);

            $this->db->commit();

            $_SESSION['success'] = "User '{$user['username']}' granted access to '{$database['database_name']}'";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to grant access: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Remove user from database
     */
    public function removeUserFromDatabase(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $mapping = $this->db->fetchAssociative(
                "SELECT ud.*, u.username, u.host, d.database_name
                 FROM {$this->prefix}mysql_user_databases ud
                 JOIN {$this->prefix}mysql_users u ON ud.user_id = u.id
                 JOIN {$this->prefix}mysql_databases d ON ud.database_id = d.id
                 WHERE ud.id = ? AND ud.account_id = ?",
                [$id, $accountId]
            );

            if (!$mapping) {
                $_SESSION['error'] = 'Mapping not found';
                return new RedirectResponse('/user/databases');
            }

            $this->db->beginTransaction();

            // Delete mapping
            $this->db->delete($this->prefix . 'mysql_user_databases', ['id' => $id]);

            // Revoke MySQL privileges
            $this->revokeMySQLPrivileges($mapping['username'], $mapping['database_name'], $mapping['host']);

            $this->db->commit();

            $_SESSION['success'] = 'User access revoked successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to revoke access: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/databases');
    }

    /**
     * Get user's account ID
     */
    private function getUserAccountId(): ?int
    {
        $user = Auth::user();

        $account = $this->db->fetchAssociative(
            "SELECT id FROM {$this->prefix}accounts WHERE user_id = ? LIMIT 1",
            [$user->getId()]
        );

        return $account ? (int)$account['id'] : null;
    }

    /**
     * Create MySQL database
     */
    private function createMySQLDatabase(string $dbName): void
    {
        try {
            // Sanitize database name
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);

            // Create database
            $this->db->executeStatement("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            logger("MySQL database created: {$safeName}");
        } catch (\Exception $e) {
            logger("Failed to create MySQL database {$dbName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Drop MySQL database
     */
    private function dropMySQLDatabase(string $dbName): void
    {
        try {
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            $this->db->executeStatement("DROP DATABASE IF EXISTS `{$safeName}`");

            logger("MySQL database dropped: {$safeName}");
        } catch (\Exception $e) {
            logger("Failed to drop MySQL database {$dbName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create MySQL user
     */
    private function createMySQLUser(string $username, string $password, string $host): void
    {
        try {
            $safeUser = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            $safeHost = $host === 'localhost' ? 'localhost' : '%';

            $this->db->executeStatement(
                "CREATE USER IF NOT EXISTS '{$safeUser}'@'{$safeHost}' IDENTIFIED BY ?",
                [$password]
            );

            logger("MySQL user created: {$safeUser}@{$safeHost}");
        } catch (\Exception $e) {
            logger("Failed to create MySQL user {$username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update MySQL user password
     */
    private function updateMySQLUserPassword(string $username, string $password, string $host): void
    {
        try {
            $safeUser = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            $safeHost = $host === 'localhost' ? 'localhost' : '%';

            $this->db->executeStatement(
                "ALTER USER '{$safeUser}'@'{$safeHost}' IDENTIFIED BY ?",
                [$password]
            );

            logger("MySQL user password updated: {$safeUser}@{$safeHost}");
        } catch (\Exception $e) {
            logger("Failed to update MySQL user password {$username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Drop MySQL user
     */
    private function dropMySQLUser(string $username, string $host): void
    {
        try {
            $safeUser = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            $safeHost = $host === 'localhost' ? 'localhost' : '%';

            $this->db->executeStatement("DROP USER IF EXISTS '{$safeUser}'@'{$safeHost}'");

            logger("MySQL user dropped: {$safeUser}@{$safeHost}");
        } catch (\Exception $e) {
            logger("Failed to drop MySQL user {$username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Grant MySQL privileges
     */
    private function grantMySQLPrivileges(string $username, string $dbName, string $host, array $privileges): void
    {
        try {
            $safeUser = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            $safeHost = $host === 'localhost' ? 'localhost' : '%';

            // Default to ALL PRIVILEGES if none specified
            if (empty($privileges)) {
                $privileges = ['ALL PRIVILEGES'];
            }

            $privList = implode(', ', $privileges);

            $this->db->executeStatement("GRANT {$privList} ON `{$safeDb}`.* TO '{$safeUser}'@'{$safeHost}'");
            $this->db->executeStatement("FLUSH PRIVILEGES");

            logger("MySQL privileges granted: {$privList} on {$safeDb} to {$safeUser}@{$safeHost}");
        } catch (\Exception $e) {
            logger("Failed to grant MySQL privileges: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revoke MySQL privileges
     */
    private function revokeMySQLPrivileges(string $username, string $dbName, string $host): void
    {
        try {
            $safeUser = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
            $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
            $safeHost = $host === 'localhost' ? 'localhost' : '%';

            $this->db->executeStatement("REVOKE ALL PRIVILEGES ON `{$safeDb}`.* FROM '{$safeUser}'@'{$safeHost}'");
            $this->db->executeStatement("FLUSH PRIVILEGES");

            logger("MySQL privileges revoked on {$safeDb} from {$safeUser}@{$safeHost}");
        } catch (\Exception $e) {
            logger("Failed to revoke MySQL privileges: " . $e->getMessage());
            throw $e;
        }
    }
}
