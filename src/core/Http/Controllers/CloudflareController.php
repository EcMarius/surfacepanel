<?php

namespace VirPanel\Core\Http\Controllers;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Services\CloudflareService;

class CloudflareController extends Controller
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
     * Show Cloudflare integrations overview
     */
    public function index(Request $request): Response
    {
        // Get all Cloudflare integrations
        $integrations = $this->db->fetchAllAssociative(
            "SELECT cf.*, d.domain as domain_name, a.username as account_username
             FROM {$this->prefix}cloudflare_integrations cf
             LEFT JOIN {$this->prefix}domains d ON cf.domain_id = d.id
             LEFT JOIN {$this->prefix}accounts a ON cf.account_id = a.id
             ORDER BY cf.created_at DESC"
        );

        return new Response($this->template->render('admin/cloudflare/index.html.twig', [
            'integrations' => $integrations,
        ]));
    }

    /**
     * Show form to add new Cloudflare integration
     */
    public function create(Request $request): Response
    {
        // Get all domains
        $domains = $this->db->fetchAllAssociative(
            "SELECT d.*, a.username as account_username
             FROM {$this->prefix}domains d
             LEFT JOIN {$this->prefix}accounts a ON d.account_id = a.id
             WHERE d.id NOT IN (SELECT domain_id FROM {$this->prefix}cloudflare_integrations WHERE domain_id IS NOT NULL)
             ORDER BY d.domain"
        );

        return new Response($this->template->render('admin/cloudflare/create.html.twig', [
            'domains' => $domains,
        ]));
    }

    /**
     * Store new Cloudflare integration
     */
    public function store(Request $request): Response
    {
        $domainId = $request->request->get('domain_id');
        $apiToken = $request->request->get('api_token');
        $apiKey = $request->request->get('api_key', '');
        $email = $request->request->get('email', '');

        $errors = [];

        if (!$domainId) {
            $errors[] = 'Domain is required';
        }

        if (!$apiToken && (!$apiKey || !$email)) {
            $errors[] = 'Either API Token or API Key + Email are required';
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/admin/cloudflare/create');
        }

        // Get domain details
        $domain = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}domains WHERE id = ?",
            [$domainId]
        );

        if (!$domain) {
            $_SESSION['error'] = 'Domain not found';
            return new RedirectResponse('/admin/cloudflare/create');
        }

        // Verify Cloudflare credentials
        try {
            $cloudflare = new CloudflareService($apiKey, $email, $apiToken);

            if (!$cloudflare->verifyCredentials()) {
                $_SESSION['error'] = 'Invalid Cloudflare credentials';
                $_SESSION['old'] = $request->request->all();
                return new RedirectResponse('/admin/cloudflare/create');
            }

            // Get or create zone on Cloudflare
            $zone = $cloudflare->getZone($domain['domain']);

            if (!$zone) {
                $zone = $cloudflare->addZone($domain['domain']);
            }

            // Store integration in database
            $this->db->insert($this->prefix . 'cloudflare_integrations', [
                'account_id' => $domain['account_id'],
                'domain_id' => $domainId,
                'zone_id' => $zone['id'],
                'api_token' => $apiToken ? $this->encrypt($apiToken) : null,
                'api_key' => $apiKey ? $this->encrypt($apiKey) : null,
                'email' => $email,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['success'] = "Cloudflare integration added for {$domain['domain']}";
            $_SESSION['cloudflare_nameservers'] = $zone['name_servers'] ?? [];
            return new RedirectResponse('/admin/cloudflare');
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Cloudflare API error: ' . $e->getMessage();
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/admin/cloudflare/create');
        }
    }

    /**
     * Show Cloudflare integration details
     */
    public function show(Request $request, int $id): Response
    {
        $integration = $this->db->fetchAssociative(
            "SELECT cf.*, d.domain as domain_name, a.username as account_username
             FROM {$this->prefix}cloudflare_integrations cf
             LEFT JOIN {$this->prefix}domains d ON cf.domain_id = d.id
             LEFT JOIN {$this->prefix}accounts a ON cf.account_id = a.id
             WHERE cf.id = ?",
            [$id]
        );

        if (!$integration) {
            $_SESSION['error'] = 'Cloudflare integration not found';
            return new RedirectResponse('/admin/cloudflare');
        }

        // Get DNS records from Cloudflare
        try {
            $cloudflare = $this->getCloudflareService($integration);
            $dnsRecords = $cloudflare->listDnsRecords($integration['zone_id']);
            $zoneDetails = $cloudflare->getZone($integration['domain_name']);
        } catch (\Exception $e) {
            $dnsRecords = [];
            $zoneDetails = null;
            $_SESSION['error'] = 'Failed to fetch Cloudflare data: ' . $e->getMessage();
        }

        return new Response($this->template->render('admin/cloudflare/show.html.twig', [
            'integration' => $integration,
            'dnsRecords' => $dnsRecords,
            'zoneDetails' => $zoneDetails,
        ]));
    }

    /**
     * Delete Cloudflare integration
     */
    public function destroy(Request $request, int $id): Response
    {
        $integration = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}cloudflare_integrations WHERE id = ?",
            [$id]
        );

        if (!$integration) {
            $_SESSION['error'] = 'Cloudflare integration not found';
            return new RedirectResponse('/admin/cloudflare');
        }

        try {
            $this->db->delete($this->prefix . 'cloudflare_integrations', ['id' => $id]);
            $_SESSION['success'] = 'Cloudflare integration removed successfully';
            return new RedirectResponse('/admin/cloudflare');
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to remove integration: ' . $e->getMessage();
            return new RedirectResponse('/admin/cloudflare/' . $id);
        }
    }

    /**
     * Purge Cloudflare cache
     */
    public function purgeCache(Request $request, int $id): Response
    {
        $integration = $this->db->fetchAssociative(
            "SELECT cf.*, d.domain as domain_name
             FROM {$this->prefix}cloudflare_integrations cf
             LEFT JOIN {$this->prefix}domains d ON cf.domain_id = d.id
             WHERE cf.id = ?",
            [$id]
        );

        if (!$integration) {
            return new JsonResponse(['success' => false, 'error' => 'Integration not found'], 404);
        }

        try {
            $cloudflare = $this->getCloudflareService($integration);
            $success = $cloudflare->purgeCache($integration['zone_id'], true);

            if ($success) {
                return new JsonResponse(['success' => true, 'message' => 'Cache purged successfully']);
            } else {
                return new JsonResponse(['success' => false, 'error' => 'Failed to purge cache'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update SSL mode
     */
    public function updateSsl(Request $request, int $id): Response
    {
        $mode = $request->request->get('mode');
        $validModes = ['off', 'flexible', 'full', 'strict'];

        if (!in_array($mode, $validModes)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid SSL mode'], 400);
        }

        $integration = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}cloudflare_integrations WHERE id = ?",
            [$id]
        );

        if (!$integration) {
            return new JsonResponse(['success' => false, 'error' => 'Integration not found'], 404);
        }

        try {
            $cloudflare = $this->getCloudflareService($integration);
            $cloudflare->updateSslMode($integration['zone_id'], $mode);

            return new JsonResponse(['success' => true, 'message' => 'SSL mode updated']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get CloudflareService instance from integration data
     */
    private function getCloudflareService(array $integration): CloudflareService
    {
        $apiToken = $integration['api_token'] ? $this->decrypt($integration['api_token']) : null;
        $apiKey = $integration['api_key'] ? $this->decrypt($integration['api_key']) : '';
        $email = $integration['email'] ?? '';

        return new CloudflareService($apiKey, $email, $apiToken);
    }

    /**
     * Encrypt sensitive data
     */
    private function encrypt(string $data): string
    {
        $key = config('app.key', 'default-encryption-key');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt sensitive data
     */
    private function decrypt(string $data): string
    {
        $key = config('app.key', 'default-encryption-key');
        list($encrypted, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
