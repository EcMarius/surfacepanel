<?php

namespace VirPanel\Core\Http\Controllers;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;

class PackageController extends Controller
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
     * Display list of packages
     */
    public function index(Request $request): Response
    {
        $packages = $this->db->fetchAllAssociative(
            "SELECT p.*,
             (SELECT COUNT(*) FROM {$this->prefix}accounts WHERE package_id = p.id) as account_count
             FROM {$this->prefix}packages p
             ORDER BY p.created_at DESC"
        );

        return new Response($this->template->render('admin/packages/index.html.twig', [
            'packages' => $packages,
        ]));
    }

    /**
     * Show create package form
     */
    public function create(Request $request): Response
    {
        return new Response($this->template->render('admin/packages/create.html.twig'));
    }

    /**
     * Store new package
     */
    public function store(Request $request): Response
    {
        $name = $request->request->get('name');
        $description = $request->request->get('description', '');
        $diskQuota = $request->request->get('disk_quota', 0);
        $bandwidthQuota = $request->request->get('bandwidth_quota', 0);
        $maxDomains = $request->request->get('max_domains', 0);
        $maxSubdomains = $request->request->get('max_subdomains', 0);
        $maxEmails = $request->request->get('max_emails', 0);
        $maxDatabases = $request->request->get('max_databases', 0);
        $maxFtpAccounts = $request->request->get('max_ftp_accounts', 0);
        $sshAccess = $request->request->get('ssh_access', 0);
        $cronJobs = $request->request->get('cron_jobs', 0);
        $backupAccess = $request->request->get('backup_access', 1);
        $isActive = $request->request->get('is_active', 1);

        // Validation
        $errors = [];

        if (!$name || strlen($name) < 2) {
            $errors[] = 'Package name must be at least 2 characters';
        }

        // Check if package name exists
        if (!$errors) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}packages WHERE name = ?",
                [$name]
            );

            if ($exists) {
                $errors[] = 'Package name already exists';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/admin/packages/create');
        }

        // Convert to bytes/bits
        $diskQuotaBytes = ($diskQuota == 0) ? 0 : $diskQuota * 1024 * 1024; // MB to bytes
        $bandwidthQuotaBytes = ($bandwidthQuota == 0) ? 0 : $bandwidthQuota * 1024 * 1024; // MB to bytes

        // Create package
        try {
            $this->db->insert($this->prefix . 'packages', [
                'name' => $name,
                'description' => $description,
                'disk_quota' => $diskQuotaBytes,
                'bandwidth_quota' => $bandwidthQuotaBytes,
                'max_domains' => $maxDomains,
                'max_subdomains' => $maxSubdomains,
                'max_emails' => $maxEmails,
                'max_databases' => $maxDatabases,
                'max_ftp_accounts' => $maxFtpAccounts,
                'ssh_access' => $sshAccess ? 1 : 0,
                'cron_jobs' => $cronJobs ? 1 : 0,
                'backup_access' => $backupAccess ? 1 : 0,
                'is_active' => $isActive ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['success'] = "Package '{$name}' created successfully";
            return new RedirectResponse('/admin/packages');
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create package: ' . $e->getMessage();
            return new RedirectResponse('/admin/packages/create');
        }
    }

    /**
     * Show package details
     */
    public function show(Request $request, int $id): Response
    {
        $package = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE id = ?",
            [$id]
        );

        if (!$package) {
            $_SESSION['error'] = 'Package not found';
            return new RedirectResponse('/admin/packages');
        }

        // Get accounts using this package
        $accounts = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE package_id = ? ORDER BY username",
            [$id]
        );

        return new Response($this->template->render('admin/packages/show.html.twig', [
            'package' => $package,
            'accounts' => $accounts,
        ]));
    }

    /**
     * Show edit package form
     */
    public function edit(Request $request, int $id): Response
    {
        $package = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE id = ?",
            [$id]
        );

        if (!$package) {
            $_SESSION['error'] = 'Package not found';
            return new RedirectResponse('/admin/packages');
        }

        return new Response($this->template->render('admin/packages/edit.html.twig', [
            'package' => $package,
        ]));
    }

    /**
     * Update package
     */
    public function update(Request $request, int $id): Response
    {
        $package = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE id = ?",
            [$id]
        );

        if (!$package) {
            $_SESSION['error'] = 'Package not found';
            return new RedirectResponse('/admin/packages');
        }

        $name = $request->request->get('name');
        $description = $request->request->get('description', '');
        $diskQuota = $request->request->get('disk_quota', 0);
        $bandwidthQuota = $request->request->get('bandwidth_quota', 0);
        $maxDomains = $request->request->get('max_domains', 0);
        $maxSubdomains = $request->request->get('max_subdomains', 0);
        $maxEmails = $request->request->get('max_emails', 0);
        $maxDatabases = $request->request->get('max_databases', 0);
        $maxFtpAccounts = $request->request->get('max_ftp_accounts', 0);
        $sshAccess = $request->request->get('ssh_access', 0);
        $cronJobs = $request->request->get('cron_jobs', 0);
        $backupAccess = $request->request->get('backup_access', 1);
        $isActive = $request->request->get('is_active', 1);

        $errors = [];

        if (!$name || strlen($name) < 2) {
            $errors[] = 'Package name must be at least 2 characters';
        }

        // Check if package name exists (excluding current package)
        if (!$errors && $name !== $package['name']) {
            $exists = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}packages WHERE name = ? AND id != ?",
                [$name, $id]
            );

            if ($exists) {
                $errors[] = 'Package name already exists';
            }
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            return new RedirectResponse('/admin/packages/' . $id . '/edit');
        }

        // Convert to bytes/bits
        $diskQuotaBytes = ($diskQuota == 0) ? 0 : $diskQuota * 1024 * 1024;
        $bandwidthQuotaBytes = ($bandwidthQuota == 0) ? 0 : $bandwidthQuota * 1024 * 1024;

        try {
            $this->db->update($this->prefix . 'packages', [
                'name' => $name,
                'description' => $description,
                'disk_quota' => $diskQuotaBytes,
                'bandwidth_quota' => $bandwidthQuotaBytes,
                'max_domains' => $maxDomains,
                'max_subdomains' => $maxSubdomains,
                'max_emails' => $maxEmails,
                'max_databases' => $maxDatabases,
                'max_ftp_accounts' => $maxFtpAccounts,
                'ssh_access' => $sshAccess ? 1 : 0,
                'cron_jobs' => $cronJobs ? 1 : 0,
                'backup_access' => $backupAccess ? 1 : 0,
                'is_active' => $isActive ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $_SESSION['success'] = 'Package updated successfully';
            return new RedirectResponse('/admin/packages/' . $id);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update package: ' . $e->getMessage();
            return new RedirectResponse('/admin/packages/' . $id . '/edit');
        }
    }

    /**
     * Delete package
     */
    public function destroy(Request $request, int $id): Response
    {
        $package = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE id = ?",
            [$id]
        );

        if (!$package) {
            $_SESSION['error'] = 'Package not found';
            return new RedirectResponse('/admin/packages');
        }

        // Check if any accounts are using this package
        $accountCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}accounts WHERE package_id = ?",
            [$id]
        );

        if ($accountCount > 0) {
            $_SESSION['error'] = "Cannot delete package. {$accountCount} account(s) are still using it.";
            return new RedirectResponse('/admin/packages/' . $id);
        }

        try {
            $this->db->delete($this->prefix . 'packages', ['id' => $id]);

            $_SESSION['success'] = "Package '{$package['name']}' deleted successfully";
            return new RedirectResponse('/admin/packages');
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete package: ' . $e->getMessage();
            return new RedirectResponse('/admin/packages/' . $id);
        }
    }
}
