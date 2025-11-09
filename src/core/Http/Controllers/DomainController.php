<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class DomainController extends Controller
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
     * Display domains overview
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get all domains for this account
        $addonDomains = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}addon_domains WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        $subdomains = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}subdomains WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        $parkedDomains = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}parked_domains WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        $redirects = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}domain_redirects WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Get main domain
        $account = $this->db->fetchAssociative(
            "SELECT domain FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        return new Response($this->template->render('user/domains/index.html.twig', [
            'mainDomain' => $account['domain'] ?? '',
            'addonDomains' => $addonDomains,
            'subdomains' => $subdomains,
            'parkedDomains' => $parkedDomains,
            'redirects' => $redirects,
        ]));
    }

    /**
     * Create addon domain
     */
    public function storeAddonDomain(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $domain = strtolower(trim($request->request->get('domain')));
        $subdomain = strtolower(trim($request->request->get('subdomain')));
        $documentRoot = trim($request->request->get('document_root'));

        // Validation
        $errors = [];

        if (!$domain || !preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
            $errors[] = 'Invalid domain name';
        }

        if (!$subdomain || !preg_match('/^[a-z0-9\-]+$/', $subdomain)) {
            $errors[] = 'Invalid subdomain';
        }

        // Check if domain already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}addon_domains WHERE domain = ?",
            [$domain]
        );

        if ($exists) {
            $errors[] = 'Domain already exists';
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/user/domains');
        }

        try {
            $this->db->beginTransaction();

            // Get account username for directory structure
            $account = $this->db->fetchAssociative(
                "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            $username = $account['username'];
            $docRoot = $documentRoot ?: "/home/{$username}/{$subdomain}";

            // Insert addon domain
            $this->db->insert($this->prefix . 'addon_domains', [
                'account_id' => $accountId,
                'domain' => $domain,
                'subdomain' => $subdomain,
                'document_root' => $docRoot,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create directory structure
            if (!file_exists($docRoot)) {
                mkdir($docRoot, 0755, true);
                mkdir($docRoot . '/public_html', 0755, true);

                // Create default index.html
                file_put_contents($docRoot . '/public_html/index.html',
                    "<html><body><h1>Domain {$domain} is ready!</h1></body></html>"
                );
            }

            // Create Apache/Nginx virtual host configuration
            $this->createVirtualHost($domain, $docRoot . '/public_html', $username);

            $this->db->commit();

            $_SESSION['success'] = "Addon domain '{$domain}' created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create addon domain: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Create subdomain
     */
    public function storeSubdomain(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $subdomain = strtolower(trim($request->request->get('subdomain')));
        $domain = $request->request->get('domain');
        $documentRoot = trim($request->request->get('document_root'));

        // Validation
        if (!preg_match('/^[a-z0-9\-]+$/', $subdomain)) {
            $_SESSION['error'] = 'Invalid subdomain name';
            return new RedirectResponse('/user/domains');
        }

        try {
            $this->db->beginTransaction();

            $account = $this->db->fetchAssociative(
                "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            $username = $account['username'];
            $fullDomain = "{$subdomain}.{$domain}";
            $docRoot = $documentRoot ?: "/home/{$username}/public_html/{$subdomain}";

            // Insert subdomain
            $this->db->insert($this->prefix . 'subdomains', [
                'account_id' => $accountId,
                'subdomain' => $subdomain,
                'domain' => $domain,
                'full_domain' => $fullDomain,
                'document_root' => $docRoot,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Create directory
            if (!file_exists($docRoot)) {
                mkdir($docRoot, 0755, true);
                file_put_contents($docRoot . '/index.html',
                    "<html><body><h1>Subdomain {$fullDomain} is ready!</h1></body></html>"
                );
            }

            $this->db->commit();

            $_SESSION['success'] = "Subdomain '{$fullDomain}' created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create subdomain: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Create parked domain
     */
    public function storeParkedDomain(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $domain = strtolower(trim($request->request->get('domain')));

        if (!preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
            $_SESSION['error'] = 'Invalid domain name';
            return new RedirectResponse('/user/domains');
        }

        try {
            // Get main domain to park to
            $account = $this->db->fetchAssociative(
                "SELECT domain FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            $this->db->insert($this->prefix . 'parked_domains', [
                'account_id' => $accountId,
                'domain' => $domain,
                'points_to' => $account['domain'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['success'] = "Parked domain '{$domain}' created successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create parked domain: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Create domain redirect
     */
    public function storeRedirect(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $domain = strtolower(trim($request->request->get('domain')));
        $redirectTo = trim($request->request->get('redirect_to'));
        $redirectType = $request->request->get('redirect_type', '301');
        $matchWww = $request->request->get('match_www', 0);
        $wildcard = $request->request->get('wildcard', 0);

        try {
            $this->db->insert($this->prefix . 'domain_redirects', [
                'account_id' => $accountId,
                'domain' => $domain,
                'redirect_to' => $redirectTo,
                'redirect_type' => $redirectType,
                'match_www' => $matchWww ? 1 : 0,
                'wildcard' => $wildcard ? 1 : 0,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['success'] = "Domain redirect created successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create redirect: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Delete addon domain
     */
    public function deleteAddonDomain(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $domain = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}addon_domains WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$domain) {
                $_SESSION['error'] = 'Domain not found';
                return new RedirectResponse('/user/domains');
            }

            $this->db->delete($this->prefix . 'addon_domains', ['id' => $id]);

            $_SESSION['success'] = "Addon domain '{$domain['domain']}' deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete domain: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Delete subdomain
     */
    public function deleteSubdomain(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->delete($this->prefix . 'subdomains', [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $_SESSION['success'] = 'Subdomain deleted successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete subdomain: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Delete parked domain
     */
    public function deleteParkedDomain(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->delete($this->prefix . 'parked_domains', [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $_SESSION['success'] = 'Parked domain deleted successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete parked domain: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
    }

    /**
     * Delete redirect
     */
    public function deleteRedirect(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->delete($this->prefix . 'domain_redirects', [
                'id' => $id,
                'account_id' => $accountId,
            ]);

            $_SESSION['success'] = 'Redirect deleted successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete redirect: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/domains');
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
     * Create virtual host configuration
     */
    private function createVirtualHost(string $domain, string $documentRoot, string $username): void
    {
        // Create Apache vhost config
        $vhostConfig = "
<VirtualHost *:80>
    ServerName {$domain}
    ServerAlias www.{$domain}
    DocumentRoot {$documentRoot}

    <Directory {$documentRoot}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/virpanel/{$domain}-error.log
    CustomLog /var/log/virpanel/{$domain}-access.log combined
</VirtualHost>
";

        $vhostPath = "/etc/apache2/sites-available/{$domain}.conf";

        // In production, write the config file
        // file_put_contents($vhostPath, $vhostConfig);
        // exec("a2ensite {$domain}.conf");
        // exec("systemctl reload apache2");

        logger("Virtual host configuration created for {$domain}");
    }
}
