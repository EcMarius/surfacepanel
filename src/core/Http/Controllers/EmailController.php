<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class EmailController extends Controller
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
     * Display email management overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get email accounts
        $emailAccounts = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}email_accounts WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Get forwarders
        $forwarders = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}email_forwarders WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Get autoresponders
        $autoresponders = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}email_autoresponders WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Get available domains
        $account = $this->db->fetchAssociative(
            "SELECT domain FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        $domains = [$account['domain']];

        // Add addon domains
        $addonDomains = $this->db->fetchAllAssociative(
            "SELECT domain FROM {$this->prefix}addon_domains WHERE account_id = ?",
            [$accountId]
        );

        foreach ($addonDomains as $addon) {
            $domains[] = $addon['domain'];
        }

        // Calculate total disk usage
        $totalUsage = array_sum(array_column($emailAccounts, 'disk_used'));

        return new Response($this->template->render('user/email/index.html.twig', [
            'emailAccounts' => $emailAccounts,
            'forwarders' => $forwarders,
            'autoresponders' => $autoresponders,
            'domains' => $domains,
            'totalUsage' => $totalUsage,
        ]));
    }

    /**
     * Create email account
     */
    public function storeAccount(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $username = strtolower(trim($request->request->get('username')));
        $domain = trim($request->request->get('domain'));
        $password = $request->request->get('password');
        $quota = (int)$request->request->get('quota', 0);

        // Validation
        $errors = [];

        if (!preg_match('/^[a-z0-9._-]+$/', $username)) {
            $errors[] = 'Invalid email username. Use only lowercase letters, numbers, dots, hyphens and underscores.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        // Check if email already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}email_accounts WHERE email = ?",
            ["{$username}@{$domain}"]
        );

        if ($exists) {
            $errors[] = 'Email account already exists';
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/user/email');
        }

        try {
            $this->db->beginTransaction();

            $email = "{$username}@{$domain}";
            $quotaBytes = $quota * 1024 * 1024; // Convert MB to bytes

            // Insert email account
            $this->db->insert($this->prefix . 'email_accounts', [
                'account_id' => $accountId,
                'email' => $email,
                'username' => $username,
                'domain' => $domain,
                'password' => password_hash($password, PASSWORD_ARGON2ID),
                'quota' => $quotaBytes,
                'disk_used' => 0,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create mailbox directory
            $mailPath = "/var/vmail/{$domain}/{$username}";
            if (!file_exists($mailPath)) {
                mkdir($mailPath, 0700, true);
                mkdir($mailPath . '/cur', 0700);
                mkdir($mailPath . '/new', 0700);
                mkdir($mailPath . '/tmp', 0700);
            }

            // Update mail server configuration (Postfix/Dovecot)
            $this->updateMailConfig();

            $this->db->commit();

            $_SESSION['success'] = "Email account '{$email}' created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create email account: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Update email password
     */
    public function updatePassword(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();
        $password = $request->request->get('password');

        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters';
            return new RedirectResponse('/user/email');
        }

        try {
            $this->db->update($this->prefix . 'email_accounts', [
                'password' => password_hash($password, PASSWORD_ARGON2ID),
                'updated_at' => date('Y-m-d H:i:s'),
            ], [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $_SESSION['success'] = 'Email password updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update password: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Update email quota
     */
    public function updateQuota(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();
        $quota = (int)$request->request->get('quota', 0);
        $quotaBytes = $quota * 1024 * 1024;

        try {
            $this->db->update($this->prefix . 'email_accounts', [
                'quota' => $quotaBytes,
                'updated_at' => date('Y-m-d H:i:s'),
            ], [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $_SESSION['success'] = 'Email quota updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update quota: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Delete email account
     */
    public function deleteAccount(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $email = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}email_accounts WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$email) {
                $_SESSION['error'] = 'Email account not found';
                return new RedirectResponse('/user/email');
            }

            $this->db->delete($this->prefix . 'email_accounts', ['id' => $id]);

            // Delete mailbox directory
            $mailPath = "/var/vmail/{$email['domain']}/{$email['username']}";
            if (is_dir($mailPath)) {
                $this->removeDirectory($mailPath);
            }

            $_SESSION['success'] = "Email account '{$email['email']}' deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete email account: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Create email forwarder
     */
    public function storeForwarder(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $source = strtolower(trim($request->request->get('source')));
        $domain = trim($request->request->get('domain'));
        $destination = trim($request->request->get('destination'));

        $sourceEmail = $source ? "{$source}@{$domain}" : "@{$domain}"; // Catch-all if source is empty

        try {
            $this->db->insert($this->prefix . 'email_forwarders', [
                'account_id' => $accountId,
                'source' => $sourceEmail,
                'destination' => $destination,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update mail server configuration
            $this->updateMailConfig();

            $_SESSION['success'] = "Forwarder from '{$sourceEmail}' to '{$destination}' created successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create forwarder: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Delete forwarder
     */
    public function deleteForwarder(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->delete($this->prefix . 'email_forwarders', [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $this->updateMailConfig();

            $_SESSION['success'] = 'Forwarder deleted successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete forwarder: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Create autoresponder
     */
    public function storeAutoresponder(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $email = trim($request->request->get('email'));
        $subject = trim($request->request->get('subject'));
        $body = trim($request->request->get('body'));
        $startDate = $request->request->get('start_date');
        $endDate = $request->request->get('end_date');

        try {
            $this->db->insert($this->prefix . 'email_autoresponders', [
                'account_id' => $accountId,
                'email' => $email,
                'subject' => $subject,
                'body' => $body,
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['success'] = "Autoresponder for '{$email}' created successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create autoresponder: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
    }

    /**
     * Delete autoresponder
     */
    public function deleteAutoresponder(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->delete($this->prefix . 'email_autoresponders', [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $_SESSION['success'] = 'Autoresponder deleted successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete autoresponder: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/email');
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
     * Update mail server configuration
     */
    private function updateMailConfig(): void
    {
        // In production, this would regenerate Postfix/Dovecot configs
        // and reload the mail server

        // For now, just log it
        logger("Mail server configuration updated");
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
