<?php

namespace VirPanel\Core\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use Doctrine\DBAL\Connection;

/**
 * Package API Controller
 *
 * RESTful API endpoints for package management
 */
class PackageApiController extends ApiController
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
     * List all packages
     *
     * GET /api/v1/packages
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $packages = $this->db->fetchAllAssociative(
                "SELECT p.*, COUNT(a.id) as accounts_count
                 FROM {$this->prefix}packages p
                 LEFT JOIN {$this->prefix}accounts a ON p.id = a.package_id
                 GROUP BY p.id
                 ORDER BY p.name ASC"
            );

            // Parse JSON fields
            foreach ($packages as &$package) {
                $package['features'] = json_decode($package['features'] ?? '{}', true);
            }

            return $this->success($packages);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get single package
     *
     * GET /api/v1/packages/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $package = $this->db->fetchAssociative(
                "SELECT p.*, COUNT(a.id) as accounts_count
                 FROM {$this->prefix}packages p
                 LEFT JOIN {$this->prefix}accounts a ON p.id = a.package_id
                 WHERE p.id = ?
                 GROUP BY p.id",
                [$id]
            );

            if (!$package) {
                return $this->error('Package not found', 404);
            }

            $package['features'] = json_decode($package['features'] ?? '{}', true);

            return $this->success($package);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create new package
     *
     * POST /api/v1/packages
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = [
                'name' => trim($request->request->get('name', '')),
                'disk_quota' => (int)$request->request->get('disk_quota', 0),
                'bandwidth_quota' => (int)$request->request->get('bandwidth_quota', 0),
                'email_accounts' => (int)$request->request->get('email_accounts', -1),
                'databases' => (int)$request->request->get('databases', -1),
                'subdomains' => (int)$request->request->get('subdomains', -1),
                'parked_domains' => (int)$request->request->get('parked_domains', -1),
                'addon_domains' => (int)$request->request->get('addon_domains', -1),
                'ftp_accounts' => (int)$request->request->get('ftp_accounts', -1),
            ];

            $features = $request->request->get('features', []);

            // Validation
            $errors = $this->validateRequired($data, ['name']);

            if (!empty($errors)) {
                return $this->error('Validation failed', 400, $errors);
            }

            $this->db->insert($this->prefix . 'packages', [
                'name' => $data['name'],
                'disk_quota' => $data['disk_quota'],
                'bandwidth_quota' => $data['bandwidth_quota'],
                'email_accounts' => $data['email_accounts'],
                'databases' => $data['databases'],
                'subdomains' => $data['subdomains'],
                'parked_domains' => $data['parked_domains'],
                'addon_domains' => $data['addon_domains'],
                'ftp_accounts' => $data['ftp_accounts'],
                'features' => json_encode($features),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $packageId = $this->db->lastInsertId();

            return $this->success([
                'id' => $packageId,
                'name' => $data['name'],
            ], 'Package created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update package
     *
     * PUT /api/v1/packages/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $package = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}packages WHERE id = ?",
                [$id]
            );

            if (!$package) {
                return $this->error('Package not found', 404);
            }

            $updateData = [];

            $fields = [
                'name', 'disk_quota', 'bandwidth_quota', 'email_accounts',
                'databases', 'subdomains', 'parked_domains', 'addon_domains', 'ftp_accounts'
            ];

            foreach ($fields as $field) {
                if ($request->request->has($field)) {
                    $updateData[$field] = $request->request->get($field);
                }
            }

            if ($request->request->has('features')) {
                $updateData['features'] = json_encode($request->request->get('features'));
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update($this->prefix . 'packages', $updateData, ['id' => $id]);
            }

            return $this->success(null, 'Package updated successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete package
     *
     * DELETE /api/v1/packages/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // Check if package is in use
            $accountCount = (int)$this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}accounts WHERE package_id = ?",
                [$id]
            );

            if ($accountCount > 0) {
                return $this->error("Cannot delete package. It is used by {$accountCount} account(s)", 400);
            }

            $this->db->delete($this->prefix . 'packages', ['id' => $id]);

            return $this->success(null, 'Package deleted successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
