<?php

namespace VirPanel\Core\Http\Controllers;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;

class ResellerController extends Controller
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/database.php';
        $this->db = \Doctrine\DBAL\DriverManager::getConnection($config);
        $app = \VirPanel\Core\Application::getInstance();
        $this->template = new TemplateEngine($app);
        $this->prefix = $config['prefix'] ?? '';
    }

    /**
     * Display list of resellers
     */
    public function index(Request $request): Response
    {
        $resellers = $this->db->fetchAllAssociative(
            "SELECT u.*, rs.*,
             (SELECT COUNT(*) FROM {$this->prefix}accounts WHERE user_id = u.id) as account_count
             FROM {$this->prefix}users u
             LEFT JOIN {$this->prefix}reseller_settings rs ON u.id = rs.user_id
             WHERE u.role = 'reseller'
             ORDER BY u.created_at DESC"
        );

        return new Response($this->template->render('admin/resellers/index.html.twig', [
            'resellers' => $resellers,
        ]));
    }

    /**
     * Show create reseller form
     */
    public function create(Request $request): Response
    {
        return new Response($this->template->render('admin/resellers/create.html.twig'));
    }

    /**
     * Store new reseller
     */
    public function store(Request $request): Response
    {
        $username = $request->request->get('username');
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $maxAccounts = $request->request->get('max_accounts', 0);
        $diskQuota = $request->request->get('disk_quota', 0);
        $bandwidthQuota = $request->request->get('bandwidth_quota', 0);
        $allowOverselling = $request->request->get('allow_overselling', 0);

        // Validation
        $errors = [];

        if (!$username || !preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
            $errors[] = 'Username must be 3-32 characters (lowercase letters, numbers, and underscore)';
        }

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        if (!$password || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        // Check if username exists
        if (!$errors) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}users WHERE username = ?",
                [$username]
            );

            if ($exists) {
                $errors[] = 'Username already exists';
            }
        }

        // Check if email exists
        if (!$errors) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}users WHERE email = ?",
                [$email]
            );

            if ($exists) {
                $errors[] = 'Email already exists';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/admin/resellers/create');
        }

        // Create reseller
        try {
            $this->db->beginTransaction();

            // Create user
            $this->db->insert($this->prefix . 'users', [
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_ARGON2ID),
                'role' => 'reseller',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $userId = $this->db->lastInsertId();

            // Convert to bytes
            $diskQuotaBytes = ($diskQuota == 0) ? 0 : $diskQuota * 1024 * 1024 * 1024; // GB to bytes
            $bandwidthQuotaBytes = ($bandwidthQuota == 0) ? 0 : $bandwidthQuota * 1024 * 1024 * 1024; // GB to bytes

            // Create reseller settings
            $this->db->insert($this->prefix . 'reseller_settings', [
                'user_id' => $userId,
                'max_accounts' => $maxAccounts,
                'disk_quota' => $diskQuotaBytes,
                'bandwidth_quota' => $bandwidthQuotaBytes,
                'disk_used' => 0,
                'bandwidth_used' => 0,
                'allow_overselling' => $allowOverselling ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $_SESSION['success'] = "Reseller '{$username}' created successfully";
            return new RedirectResponse('/admin/resellers');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create reseller: ' . $e->getMessage();
            return new RedirectResponse('/admin/resellers/create');
        }
    }

    /**
     * Show reseller details
     */
    public function show(Request $request, int $id): Response
    {
        $reseller = $this->db->fetchAssociative(
            "SELECT u.*, rs.*
             FROM {$this->prefix}users u
             LEFT JOIN {$this->prefix}reseller_settings rs ON u.id = rs.user_id
             WHERE u.id = ? AND u.role = 'reseller'",
            [$id]
        );

        if (!$reseller) {
            $_SESSION['error'] = 'Reseller not found';
            return new RedirectResponse('/admin/resellers');
        }

        // Get reseller's accounts
        $accounts = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ? ORDER BY created_at DESC",
            [$id]
        );

        // Calculate used resources
        $diskUsed = array_sum(array_column($accounts, 'disk_used'));
        $bandwidthUsed = array_sum(array_column($accounts, 'bandwidth_used'));

        return new Response($this->template->render('admin/resellers/show.html.twig', [
            'reseller' => $reseller,
            'accounts' => $accounts,
            'diskUsed' => $diskUsed,
            'bandwidthUsed' => $bandwidthUsed,
        ]));
    }

    /**
     * Show edit reseller form
     */
    public function edit(Request $request, int $id): Response
    {
        $reseller = $this->db->fetchAssociative(
            "SELECT u.*, rs.*
             FROM {$this->prefix}users u
             LEFT JOIN {$this->prefix}reseller_settings rs ON u.id = rs.user_id
             WHERE u.id = ? AND u.role = 'reseller'",
            [$id]
        );

        if (!$reseller) {
            $_SESSION['error'] = 'Reseller not found';
            return new RedirectResponse('/admin/resellers');
        }

        return new Response($this->template->render('admin/resellers/edit.html.twig', [
            'reseller' => $reseller,
        ]));
    }

    /**
     * Update reseller
     */
    public function update(Request $request, int $id): Response
    {
        $reseller = $this->db->fetchAssociative(
            "SELECT u.*, rs.*
             FROM {$this->prefix}users u
             LEFT JOIN {$this->prefix}reseller_settings rs ON u.id = rs.user_id
             WHERE u.id = ? AND u.role = 'reseller'",
            [$id]
        );

        if (!$reseller) {
            $_SESSION['error'] = 'Reseller not found';
            return new RedirectResponse('/admin/resellers');
        }

        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $maxAccounts = $request->request->get('max_accounts', 0);
        $diskQuota = $request->request->get('disk_quota', 0);
        $bandwidthQuota = $request->request->get('bandwidth_quota', 0);
        $allowOverselling = $request->request->get('allow_overselling', 0);

        $errors = [];

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        if ($password && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        // Check if email exists (excluding current reseller)
        if ($email && $email !== $reseller['email']) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}users WHERE email = ? AND id != ?",
                [$email, $id]
            );

            if ($exists) {
                $errors[] = 'Email already exists';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            return new RedirectResponse('/admin/resellers/' . $id . '/edit');
        }

        try {
            $this->db->beginTransaction();

            // Update user
            $userData = [
                'email' => $email,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($password) {
                $userData['password'] = password_hash($password, PASSWORD_ARGON2ID);
            }

            $this->db->update($this->prefix . 'users', $userData, ['id' => $id]);

            // Convert to bytes
            $diskQuotaBytes = ($diskQuota == 0) ? 0 : $diskQuota * 1024 * 1024 * 1024;
            $bandwidthQuotaBytes = ($bandwidthQuota == 0) ? 0 : $bandwidthQuota * 1024 * 1024 * 1024;

            // Update reseller settings
            $this->db->update($this->prefix . 'reseller_settings', [
                'max_accounts' => $maxAccounts,
                'disk_quota' => $diskQuotaBytes,
                'bandwidth_quota' => $bandwidthQuotaBytes,
                'allow_overselling' => $allowOverselling ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['user_id' => $id]);

            $this->db->commit();

            $_SESSION['success'] = 'Reseller updated successfully';
            return new RedirectResponse('/admin/resellers/' . $id);
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to update reseller: ' . $e->getMessage();
            return new RedirectResponse('/admin/resellers/' . $id . '/edit');
        }
    }

    /**
     * Suspend reseller
     */
    public function suspend(Request $request, int $id): Response
    {
        $this->db->update($this->prefix . 'users', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id, 'role' => 'reseller']);

        $_SESSION['success'] = 'Reseller suspended successfully';
        return new RedirectResponse('/admin/resellers/' . $id);
    }

    /**
     * Unsuspend reseller
     */
    public function unsuspend(Request $request, int $id): Response
    {
        $this->db->update($this->prefix . 'users', [
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id, 'role' => 'reseller']);

        $_SESSION['success'] = 'Reseller unsuspended successfully';
        return new RedirectResponse('/admin/resellers/' . $id);
    }

    /**
     * Delete reseller
     */
    public function destroy(Request $request, int $id): Response
    {
        // Check if reseller has accounts
        $accountCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}accounts WHERE user_id = ?",
            [$id]
        );

        if ($accountCount > 0) {
            $_SESSION['error'] = "Cannot delete reseller. {$accountCount} account(s) exist. Please reassign or delete them first.";
            return new RedirectResponse('/admin/resellers/' . $id);
        }

        try {
            $this->db->beginTransaction();

            // Delete reseller settings
            $this->db->delete($this->prefix . 'reseller_settings', ['user_id' => $id]);

            // Delete user
            $this->db->delete($this->prefix . 'users', ['id' => $id, 'role' => 'reseller']);

            $this->db->commit();

            $_SESSION['success'] = 'Reseller deleted successfully';
            return new RedirectResponse('/admin/resellers');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete reseller: ' . $e->getMessage();
            return new RedirectResponse('/admin/resellers/' . $id);
        }
    }
}
