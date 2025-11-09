<?php

namespace VirPanel\Core\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use Doctrine\DBAL\Connection;

/**
 * Domain API Controller
 *
 * RESTful API endpoints for domain management
 */
class DomainApiController extends ApiController
{
    private Connection $db;
    private string $prefix;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->prefix = config('database.prefix', 'vp_');
    }

    /**
     * List all domains for an account
     *
     * GET /api/v1/accounts/{accountId}/domains
     */
    public function index(Request $request, int $accountId): JsonResponse
    {
        try {
            // Get main domain
            $account = $this->db->fetchAssociative(
                "SELECT id, domain FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            if (!$account) {
                return $this->error('Account not found', 404);
            }

            $domains = [
                [
                    'type' => 'main',
                    'domain' => $account['domain'],
                    'document_root' => "/home/{$account['domain']}/public_html",
                ]
            ];

            // Get addon domains
            $addonDomains = $this->db->fetchAllAssociative(
                "SELECT * FROM {$this->prefix}addon_domains WHERE account_id = ?",
                [$accountId]
            );

            foreach ($addonDomains as $addon) {
                $domains[] = [
                    'id' => $addon['id'],
                    'type' => 'addon',
                    'domain' => $addon['domain'],
                    'subdomain' => $addon['subdomain'],
                    'document_root' => $addon['document_root'],
                    'created_at' => $addon['created_at'],
                ];
            }

            // Get subdomains
            $subdomains = $this->db->fetchAllAssociative(
                "SELECT * FROM {$this->prefix}subdomains WHERE account_id = ?",
                [$accountId]
            );

            foreach ($subdomains as $sub) {
                $domains[] = [
                    'id' => $sub['id'],
                    'type' => 'subdomain',
                    'subdomain' => $sub['subdomain'],
                    'domain' => $sub['full_domain'],
                    'document_root' => $sub['document_root'],
                    'created_at' => $sub['created_at'],
                ];
            }

            // Get parked domains
            $parkedDomains = $this->db->fetchAllAssociative(
                "SELECT * FROM {$this->prefix}parked_domains WHERE account_id = ?",
                [$accountId]
            );

            foreach ($parkedDomains as $parked) {
                $domains[] = [
                    'id' => $parked['id'],
                    'type' => 'parked',
                    'domain' => $parked['domain'],
                    'created_at' => $parked['created_at'],
                ];
            }

            return $this->success($domains);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create addon domain
     *
     * POST /api/v1/accounts/{accountId}/domains/addon
     */
    public function storeAddon(Request $request, int $accountId): JsonResponse
    {
        try {
            $domain = strtolower(trim($request->request->get('domain', '')));
            $subdomain = strtolower(trim($request->request->get('subdomain', '')));
            $documentRoot = trim($request->request->get('document_root', ''));

            // Validation
            $errors = $this->validateRequired(
                ['domain' => $domain, 'subdomain' => $subdomain],
                ['domain', 'subdomain']
            );

            if (!empty($errors)) {
                return $this->error('Validation failed', 400, $errors);
            }

            $this->db->insert($this->prefix . 'addon_domains', [
                'account_id' => $accountId,
                'domain' => $domain,
                'subdomain' => $subdomain,
                'document_root' => $documentRoot,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $domainId = $this->db->lastInsertId();

            return $this->success([
                'id' => $domainId,
                'domain' => $domain,
            ], 'Addon domain created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create subdomain
     *
     * POST /api/v1/accounts/{accountId}/domains/subdomain
     */
    public function storeSubdomain(Request $request, int $accountId): JsonResponse
    {
        try {
            $subdomain = strtolower(trim($request->request->get('subdomain', '')));
            $domain = strtolower(trim($request->request->get('domain', '')));
            $documentRoot = trim($request->request->get('document_root', ''));

            // Validation
            $errors = $this->validateRequired(
                ['subdomain' => $subdomain, 'domain' => $domain],
                ['subdomain', 'domain']
            );

            if (!empty($errors)) {
                return $this->error('Validation failed', 400, $errors);
            }

            $fullDomain = "{$subdomain}.{$domain}";

            $this->db->insert($this->prefix . 'subdomains', [
                'account_id' => $accountId,
                'subdomain' => $subdomain,
                'domain' => $domain,
                'full_domain' => $fullDomain,
                'document_root' => $documentRoot,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $subdomainId = $this->db->lastInsertId();

            return $this->success([
                'id' => $subdomainId,
                'full_domain' => $fullDomain,
            ], 'Subdomain created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create parked domain
     *
     * POST /api/v1/accounts/{accountId}/domains/parked
     */
    public function storeParked(Request $request, int $accountId): JsonResponse
    {
        try {
            $domain = strtolower(trim($request->request->get('domain', '')));

            // Validation
            $errors = $this->validateRequired(['domain' => $domain], ['domain']);

            if (!empty($errors)) {
                return $this->error('Validation failed', 400, $errors);
            }

            $this->db->insert($this->prefix . 'parked_domains', [
                'account_id' => $accountId,
                'domain' => $domain,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $parkedId = $this->db->lastInsertId();

            return $this->success([
                'id' => $parkedId,
                'domain' => $domain,
            ], 'Parked domain created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete addon domain
     *
     * DELETE /api/v1/domains/addon/{id}
     */
    public function deleteAddon(Request $request, int $id): JsonResponse
    {
        try {
            $this->db->delete($this->prefix . 'addon_domains', ['id' => $id]);

            return $this->success(null, 'Addon domain deleted successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete subdomain
     *
     * DELETE /api/v1/domains/subdomain/{id}
     */
    public function deleteSubdomain(Request $request, int $id): JsonResponse
    {
        try {
            $this->db->delete($this->prefix . 'subdomains', ['id' => $id]);

            return $this->success(null, 'Subdomain deleted successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete parked domain
     *
     * DELETE /api/v1/domains/parked/{id}
     */
    public function deleteParked(Request $request, int $id): JsonResponse
    {
        try {
            $this->db->delete($this->prefix . 'parked_domains', ['id' => $id]);

            return $this->success(null, 'Parked domain deleted successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
