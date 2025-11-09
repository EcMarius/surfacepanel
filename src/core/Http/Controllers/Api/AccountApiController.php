<?php

namespace VirPanel\Core\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use Doctrine\DBAL\Connection;

/**
 * Account API Controller
 *
 * RESTful API endpoints for account management
 */
class AccountApiController extends ApiController
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
     * List all accounts
     *
     * GET /api/v1/accounts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int)$request->query->get('page', 1));
            $perPage = min(100, max(1, (int)$request->query->get('per_page', 20)));
            $status = $request->query->get('status');
            $search = $request->query->get('search');

            // Build query
            $qb = $this->db->createQueryBuilder()
                ->select('a.*', 'u.email as user_email', 'p.name as package_name')
                ->from($this->prefix . 'accounts', 'a')
                ->leftJoin('a', $this->prefix . 'users', 'u', 'a.user_id = u.id')
                ->leftJoin('a', $this->prefix . 'packages', 'p', 'a.package_id = p.id')
                ->orderBy('a.created_at', 'DESC');

            if ($status) {
                $qb->andWhere('a.status = :status')
                   ->setParameter('status', $status);
            }

            if ($search) {
                $qb->andWhere('(a.username LIKE :search OR a.domain LIKE :search OR u.email LIKE :search)')
                   ->setParameter('search', '%' . $search . '%');
            }

            // Get total count
            $total = (int)$this->db->createQueryBuilder()
                ->select('COUNT(*)')
                ->from($this->prefix . 'accounts', 'a')
                ->leftJoin('a', $this->prefix . 'users', 'u', 'a.user_id = u.id')
                ->where($qb->getQueryPart('where'))
                ->setParameters($qb->getParameters())
                ->fetchOne();

            // Get paginated results
            $accounts = $qb->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage)
                ->fetchAllAssociative();

            return $this->paginate($accounts, $total, $page, $perPage);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get single account
     *
     * GET /api/v1/accounts/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $account = $this->db->fetchAssociative(
                "SELECT a.*, u.email as user_email, p.name as package_name
                 FROM {$this->prefix}accounts a
                 LEFT JOIN {$this->prefix}users u ON a.user_id = u.id
                 LEFT JOIN {$this->prefix}packages p ON a.package_id = p.id
                 WHERE a.id = ?",
                [$id]
            );

            if (!$account) {
                return $this->error('Account not found', 404);
            }

            // Get account statistics
            $account['stats'] = [
                'disk_usage' => $this->getAccountDiskUsage($account['username']),
                'bandwidth_usage' => 0, // TODO: Implement bandwidth tracking
                'domains_count' => $this->getDomainsCount($id),
                'email_accounts' => $this->getEmailAccountsCount($id),
                'databases_count' => $this->getDatabasesCount($id),
                'ssl_certificates' => $this->getSSLCount($id),
            ];

            return $this->success($account);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create new account
     *
     * POST /api/v1/accounts
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = [
                'username' => strtolower(trim($request->request->get('username', ''))),
                'domain' => strtolower(trim($request->request->get('domain', ''))),
                'password' => $request->request->get('password', ''),
                'email' => trim($request->request->get('email', '')),
                'package_id' => (int)$request->request->get('package_id', 0),
            ];

            // Validation
            $errors = $this->validateRequired($data, ['username', 'domain', 'password', 'email', 'package_id']);

            if (!preg_match('/^[a-z0-9_]{3,16}$/', $data['username'])) {
                $errors['username'] = 'Username must be 3-16 characters (a-z, 0-9, _)';
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email address';
            }

            if (!empty($errors)) {
                return $this->error('Validation failed', 400, $errors);
            }

            $this->db->beginTransaction();

            // Create user
            $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);

            $this->db->insert($this->prefix . 'users', [
                'email' => $data['email'],
                'password' => $hashedPassword,
                'role' => 'user',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $userId = $this->db->lastInsertId();

            // Get package details
            $package = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}packages WHERE id = ?",
                [$data['package_id']]
            );

            if (!$package) {
                throw new \Exception('Package not found');
            }

            // Create account
            $this->db->insert($this->prefix . 'accounts', [
                'user_id' => $userId,
                'package_id' => $data['package_id'],
                'username' => $data['username'],
                'domain' => $data['domain'],
                'disk_quota' => $package['disk_quota'],
                'bandwidth_quota' => $package['bandwidth_quota'],
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $accountId = $this->db->lastInsertId();

            $this->db->commit();

            logger("Account created via API: {$data['username']}");

            return $this->success([
                'id' => $accountId,
                'username' => $data['username'],
                'domain' => $data['domain'],
            ], 'Account created successfully', 201);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Update account
     *
     * PUT /api/v1/accounts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $account = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
                [$id]
            );

            if (!$account) {
                return $this->error('Account not found', 404);
            }

            $updateData = [];

            if ($request->request->has('package_id')) {
                $updateData['package_id'] = (int)$request->request->get('package_id');
            }

            if ($request->request->has('disk_quota')) {
                $updateData['disk_quota'] = (int)$request->request->get('disk_quota');
            }

            if ($request->request->has('bandwidth_quota')) {
                $updateData['bandwidth_quota'] = (int)$request->request->get('bandwidth_quota');
            }

            if (!empty($updateData)) {
                $updateData['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update($this->prefix . 'accounts', $updateData, ['id' => $id]);
            }

            return $this->success(null, 'Account updated successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Suspend account
     *
     * POST /api/v1/accounts/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        try {
            $reason = trim($request->request->get('reason', ''));

            $this->db->update($this->prefix . 'accounts', [
                'status' => 'suspended',
                'suspend_reason' => $reason,
                'suspended_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            logger("Account suspended via API: ID {$id}");

            return $this->success(null, 'Account suspended successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Unsuspend account
     *
     * POST /api/v1/accounts/{id}/unsuspend
     */
    public function unsuspend(Request $request, int $id): JsonResponse
    {
        try {
            $this->db->update($this->prefix . 'accounts', [
                'status' => 'active',
                'suspend_reason' => null,
                'suspended_at' => null,
            ], ['id' => $id]);

            logger("Account unsuspended via API: ID {$id}");

            return $this->success(null, 'Account unsuspended successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete account
     *
     * DELETE /api/v1/accounts/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $account = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
                [$id]
            );

            if (!$account) {
                return $this->error('Account not found', 404);
            }

            $this->db->beginTransaction();

            // Delete related data
            $this->db->delete($this->prefix . 'addon_domains', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'subdomains', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'parked_domains', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'email_accounts', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'email_forwarders', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'databases', ['account_id' => $id]);
            $this->db->delete($this->prefix . 'ssl_certificates', ['account_id' => $id]);

            // Delete account
            $this->db->delete($this->prefix . 'accounts', ['id' => $id]);

            $this->db->commit();

            logger("Account deleted via API: {$account['username']}");

            return $this->success(null, 'Account deleted successfully');
        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Get account disk usage
     */
    private function getAccountDiskUsage(string $username): int
    {
        $homePath = "/home/{$username}";

        if (!is_dir($homePath)) {
            return 0;
        }

        // In production, use: du -sb
        // For now, return 0
        return 0;
    }

    /**
     * Get domains count
     */
    private function getDomainsCount(int $accountId): int
    {
        $addon = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}addon_domains WHERE account_id = ?",
            [$accountId]
        );

        $sub = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}subdomains WHERE account_id = ?",
            [$accountId]
        );

        $parked = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}parked_domains WHERE account_id = ?",
            [$accountId]
        );

        return 1 + $addon + $sub + $parked; // +1 for main domain
    }

    /**
     * Get email accounts count
     */
    private function getEmailAccountsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}email_accounts WHERE account_id = ?",
            [$accountId]
        );
    }

    /**
     * Get databases count
     */
    private function getDatabasesCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}databases WHERE account_id = ?",
            [$accountId]
        );
    }

    /**
     * Get SSL certificates count
     */
    private function getSSLCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ssl_certificates WHERE account_id = ? AND status = 'active'",
            [$accountId]
        );
    }
}
