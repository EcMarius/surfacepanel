<?php

namespace VirPanel\Core\Http\Controllers;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;

class AccountController extends Controller
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
     * Display list of accounts
     */
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');

        // Build query
        $qb = $this->db->createQueryBuilder();
        $qb->select('a.*', 'p.name as package_name', 'u.username as owner_username')
            ->from($this->prefix . 'accounts', 'a')
            ->leftJoin('a', $this->prefix . 'packages', 'p', 'a.package_id = p.id')
            ->leftJoin('a', $this->prefix . 'users', 'u', 'a.user_id = u.id')
            ->setMaxResults($perPage)
            ->setFirstResult($offset)
            ->orderBy('a.created_at', 'DESC');

        if ($search) {
            $qb->andWhere('a.username LIKE :search OR a.domain LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        $accounts = $qb->executeQuery()->fetchAllAssociative();

        // Get total count
        $countQb = $this->db->createQueryBuilder();
        $countQb->select('COUNT(*)')
            ->from($this->prefix . 'accounts', 'a');

        if ($search) {
            $countQb->andWhere('a.username LIKE :search OR a.domain LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $countQb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        $total = (int) $countQb->executeQuery()->fetchOne();
        $totalPages = ceil($total / $perPage);

        return new Response($this->template->render('admin/accounts/index.html.twig', [
            'accounts' => $accounts,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
            'status' => $status,
        ]));
    }

    /**
     * Show create account form
     */
    public function create(Request $request): Response
    {
        // Get packages for dropdown
        $packages = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE is_active = 1 ORDER BY name"
        );

        return new Response($this->template->render('admin/accounts/create.html.twig', [
            'packages' => $packages,
        ]));
    }

    /**
     * Store new account
     */
    public function store(Request $request): Response
    {
        $username = $request->request->get('username');
        $domain = $request->request->get('domain');
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $packageId = $request->request->get('package_id');

        // Validation
        $errors = [];

        if (!$username || !preg_match('/^[a-z0-9]{3,16}$/', $username)) {
            $errors[] = 'Username must be 3-16 characters (lowercase letters and numbers only)';
        }

        if (!$domain || !filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $errors[] = 'Invalid domain name';
        }

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        if (!$password || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!$packageId) {
            $errors[] = 'Package is required';
        }

        // Check if username exists
        if (!$errors) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}accounts WHERE username = ?",
                [$username]
            );

            if ($exists) {
                $errors[] = 'Username already exists';
            }
        }

        // Check if domain exists
        if (!$errors) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}accounts WHERE domain = ?",
                [$domain]
            );

            if ($exists) {
                $errors[] = 'Domain already exists';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/admin/accounts/create');
        }

        // Get package details
        $package = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE id = ?",
            [$packageId]
        );

        if (!$package) {
            $_SESSION['error'] = 'Invalid package selected';
            return new RedirectResponse('/admin/accounts/create');
        }

        // Create account
        try {
            $this->db->beginTransaction();

            $this->db->insert($this->prefix . 'accounts', [
                'username' => $username,
                'domain' => $domain,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_ARGON2ID),
                'package_id' => $packageId,
                'user_id' => Auth::id(),
                'status' => 'active',
                'disk_quota' => $package['disk_quota'],
                'bandwidth_quota' => $package['bandwidth_quota'],
                'disk_used' => 0,
                'bandwidth_used' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $accountId = $this->db->lastInsertId();

            // Create home directory
            $homeDir = '/home/' . $username;
            if (!is_dir($homeDir)) {
                mkdir($homeDir, 0755, true);
                mkdir($homeDir . '/public_html', 0755, true);
                mkdir($homeDir . '/logs', 0755, true);
                mkdir($homeDir . '/tmp', 0755, true);
            }

            // Create domain entry
            $this->db->insert($this->prefix . 'domains', [
                'account_id' => $accountId,
                'domain' => $domain,
                'type' => 'main',
                'document_root' => $homeDir . '/public_html',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            $_SESSION['success'] = "Account '{$username}' created successfully";
            return new RedirectResponse('/admin/accounts');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create account: ' . $e->getMessage();
            return new RedirectResponse('/admin/accounts/create');
        }
    }

    /**
     * Show account details
     */
    public function show(Request $request, int $id): Response
    {
        $account = $this->db->fetchAssociative(
            "SELECT a.*, p.name as package_name, u.username as owner_username
             FROM {$this->prefix}accounts a
             LEFT JOIN {$this->prefix}packages p ON a.package_id = p.id
             LEFT JOIN {$this->prefix}users u ON a.user_id = u.id
             WHERE a.id = ?",
            [$id]
        );

        if (!$account) {
            $_SESSION['error'] = 'Account not found';
            return new RedirectResponse('/admin/accounts');
        }

        // Get domains
        $domains = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}domains WHERE account_id = ? ORDER BY type, domain",
            [$id]
        );

        // Get database count
        $dbCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}databases WHERE account_id = ?",
            [$id]
        );

        // Get email count
        $emailCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}email_accounts WHERE account_id = ?",
            [$id]
        );

        return new Response($this->template->render('admin/accounts/show.html.twig', [
            'account' => $account,
            'domains' => $domains,
            'dbCount' => $dbCount,
            'emailCount' => $emailCount,
        ]));
    }

    /**
     * Show edit account form
     */
    public function edit(Request $request, int $id): Response
    {
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            $_SESSION['error'] = 'Account not found';
            return new RedirectResponse('/admin/accounts');
        }

        $packages = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE is_active = 1 ORDER BY name"
        );

        return new Response($this->template->render('admin/accounts/edit.html.twig', [
            'account' => $account,
            'packages' => $packages,
        ]));
    }

    /**
     * Update account
     */
    public function update(Request $request, int $id): Response
    {
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            $_SESSION['error'] = 'Account not found';
            return new RedirectResponse('/admin/accounts');
        }

        $email = $request->request->get('email');
        $packageId = $request->request->get('package_id');
        $password = $request->request->get('password');

        $errors = [];

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        if ($password && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            return new RedirectResponse('/admin/accounts/' . $id . '/edit');
        }

        $updateData = [
            'email' => $email,
            'package_id' => $packageId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($password) {
            $updateData['password'] = password_hash($password, PASSWORD_ARGON2ID);
        }

        $this->db->update($this->prefix . 'accounts', $updateData, ['id' => $id]);

        $_SESSION['success'] = 'Account updated successfully';
        return new RedirectResponse('/admin/accounts/' . $id);
    }

    /**
     * Suspend account
     */
    public function suspend(Request $request, int $id): Response
    {
        $reason = $request->request->get('reason', 'Administrative action');

        $this->db->update($this->prefix . 'accounts', [
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $_SESSION['success'] = 'Account suspended successfully';
        return new RedirectResponse('/admin/accounts/' . $id);
    }

    /**
     * Unsuspend account
     */
    public function unsuspend(Request $request, int $id): Response
    {
        $this->db->update($this->prefix . 'accounts', [
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $_SESSION['success'] = 'Account unsuspended successfully';
        return new RedirectResponse('/admin/accounts/' . $id);
    }

    /**
     * Terminate account
     */
    public function destroy(Request $request, int $id): Response
    {
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            $_SESSION['error'] = 'Account not found';
            return new RedirectResponse('/admin/accounts');
        }

        try {
            $this->db->beginTransaction();

            // Delete related records
            $this->db->delete($this->prefix . 'domains', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'email_accounts', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'databases', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'ftp_accounts', ['account_id' => $id]);

            // Delete account
            $this->db->delete($this->prefix . 'accounts', ['id' => $id]);

            $this->db->commit();

            $_SESSION['success'] = "Account '{$account['username']}' terminated successfully";
            return new RedirectResponse('/admin/accounts');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to terminate account: ' . $e->getMessage();
            return new RedirectResponse('/admin/accounts/' . $id);
        }
    }
}
