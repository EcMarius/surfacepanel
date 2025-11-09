<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class FTPController extends Controller
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
     * Display FTP accounts overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get account details
        $account = $this->db->fetchAssociative(
            "SELECT a.*, p.ftp_accounts as ftp_limit FROM {$this->prefix}accounts a
             LEFT JOIN {$this->prefix}packages p ON a.package_id = p.id
             WHERE a.id = ?",
            [$accountId]
        );

        // Get all FTP accounts
        $ftpAccounts = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}ftp_accounts WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Count current FTP accounts
        $currentCount = count($ftpAccounts);
        $ftpLimit = $account['ftp_limit'] ?? -1; // -1 = unlimited

        return new Response($this->template->render('user/ftp/index.html.twig', [
            'ftp_accounts' => $ftpAccounts,
            'account' => $account,
            'current_count' => $currentCount,
            'ftp_limit' => $ftpLimit,
            'can_create' => $ftpLimit === -1 || $currentCount < $ftpLimit,
        ]));
    }

    /**
     * Create new FTP account
     */
    public function store(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        $username = strtolower(trim($request->request->get('username')));
        $password = $request->request->get('password');
        $directory = trim($request->request->get('directory', '/'));
        $quota = (int)$request->request->get('quota', 0); // MB, 0 = unlimited

        // Get account details
        $account = $this->db->fetchAssociative(
            "SELECT a.*, u.email, p.ftp_accounts as ftp_limit FROM {$this->prefix}accounts a
             LEFT JOIN {$this->prefix}users u ON a.user_id = u.id
             LEFT JOIN {$this->prefix}packages p ON a.package_id = p.id
             WHERE a.id = ?",
            [$accountId]
        );

        // Check FTP account limit
        $currentCount = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ftp_accounts WHERE account_id = ?",
            [$accountId]
        );

        $ftpLimit = $account['ftp_limit'] ?? -1;
        if ($ftpLimit !== -1 && $currentCount >= $ftpLimit) {
            $_SESSION['error'] = "FTP account limit reached ({$ftpLimit} accounts)";
            return new RedirectResponse('/user/ftp');
        }

        // Validation
        if (!$username || !$password) {
            $_SESSION['error'] = 'Username and password are required';
            return new RedirectResponse('/user/ftp');
        }

        if (!preg_match('/^[a-z0-9_]{3,16}$/', $username)) {
            $_SESSION['error'] = 'Username must be 3-16 characters (a-z, 0-9, _)';
            return new RedirectResponse('/user/ftp');
        }

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters';
            return new RedirectResponse('/user/ftp');
        }

        // Create full FTP username (prefix with account username)
        $ftpUsername = $account['username'] . '_' . $username;

        // Check if FTP username already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ftp_accounts WHERE username = ?",
            [$ftpUsername]
        );

        if ($exists) {
            $_SESSION['error'] = "FTP account {$ftpUsername} already exists";
            return new RedirectResponse('/user/ftp');
        }

        // Validate directory path
        $homeDir = "/home/{$account['username']}";
        $fullPath = $homeDir . '/' . ltrim($directory, '/');

        // Prevent directory traversal
        $realPath = realpath(dirname($fullPath));
        if ($realPath && strpos($realPath, $homeDir) !== 0) {
            $_SESSION['error'] = 'Invalid directory path';
            return new RedirectResponse('/user/ftp');
        }

        try {
            $this->db->beginTransaction();

            // Hash password (using crypt for ProFTPD compatibility)
            $hashedPassword = crypt($password, '$6$' . substr(md5(random_bytes(16)), 0, 16));

            // Get system UID/GID for the account
            $uid = $this->getAccountUID($account['username']);
            $gid = $this->getAccountGID($account['username']);

            // Create FTP account
            $this->db->insert($this->prefix . 'ftp_accounts', [
                'account_id' => $accountId,
                'username' => $ftpUsername,
                'password' => $hashedPassword,
                'uid' => $uid,
                'gid' => $gid,
                'home_directory' => $fullPath,
                'shell' => '/sbin/nologin',
                'quota' => $quota,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create directory if it doesn't exist
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                chown($fullPath, $uid);
                chgrp($fullPath, $gid);
            }

            // Update ProFTPD configuration
            $this->updateProFTPDConfig();

            $this->db->commit();

            logger("FTP account created: {$ftpUsername}");

            $_SESSION['success'] = "FTP account {$ftpUsername} created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create FTP account: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ftp');
    }

    /**
     * Update FTP account password
     */
    public function updatePassword(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();
        $password = $request->request->get('password');

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters';
            return new RedirectResponse('/user/ftp');
        }

        try {
            // Verify FTP account belongs to user
            $ftpAccount = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ftp_accounts WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$ftpAccount) {
                $_SESSION['error'] = 'FTP account not found';
                return new RedirectResponse('/user/ftp');
            }

            // Hash password
            $hashedPassword = crypt($password, '$6$' . substr(md5(random_bytes(16)), 0, 16));

            // Update password
            $this->db->update($this->prefix . 'ftp_accounts', [
                'password' => $hashedPassword,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            logger("FTP password updated: {$ftpAccount['username']}");

            $_SESSION['success'] = 'FTP password updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update FTP password: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ftp');
    }

    /**
     * Update FTP account quota
     */
    public function updateQuota(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();
        $quota = (int)$request->request->get('quota', 0);

        try {
            // Verify FTP account belongs to user
            $ftpAccount = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ftp_accounts WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$ftpAccount) {
                $_SESSION['error'] = 'FTP account not found';
                return new RedirectResponse('/user/ftp');
            }

            // Update quota
            $this->db->update($this->prefix . 'ftp_accounts', [
                'quota' => $quota,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            logger("FTP quota updated: {$ftpAccount['username']} = {$quota}MB");

            $_SESSION['success'] = 'FTP quota updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update FTP quota: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ftp');
    }

    /**
     * Delete FTP account
     */
    public function delete(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->beginTransaction();

            // Verify FTP account belongs to user
            $ftpAccount = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ftp_accounts WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$ftpAccount) {
                $_SESSION['error'] = 'FTP account not found';
                return new RedirectResponse('/user/ftp');
            }

            // Delete FTP account
            $this->db->delete($this->prefix . 'ftp_accounts', ['id' => $id]);

            // Update ProFTPD configuration
            $this->updateProFTPDConfig();

            $this->db->commit();

            logger("FTP account deleted: {$ftpAccount['username']}");

            $_SESSION['success'] = "FTP account {$ftpAccount['username']} deleted successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete FTP account: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ftp');
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
     * Get system UID for account
     */
    private function getAccountUID(string $username): int
    {
        // In production, this would look up the actual system user
        // For now, return a base UID + account ID
        return 10000; // Default UID range for virtual users
    }

    /**
     * Get system GID for account
     */
    private function getAccountGID(string $username): int
    {
        // In production, this would look up the actual system group
        // For now, return a base GID
        return 10000; // Default GID for virtual users
    }

    /**
     * Update ProFTPD configuration
     */
    private function updateProFTPDConfig(): void
    {
        // In production, this would:
        // 1. Generate /etc/proftpd/sql.conf with database connection
        // 2. Reload ProFTPD service
        // Example: exec('systemctl reload proftpd');

        logger('ProFTPD configuration update requested');
    }
}
