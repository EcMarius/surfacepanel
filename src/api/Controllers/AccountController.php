<?php

namespace VirPanel\Api\Controllers;

use VirPanel\Api\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Account API Controller
 *
 * Handles account management API endpoints
 */
class AccountController extends Controller
{
    /**
     * List all accounts
     *
     * GET /api/v1/accounts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 20);
        $search = $request->query->get('search');
        $status = $request->query->get('status');

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        // Build query
        $queryBuilder = $db->createQueryBuilder()
            ->select('a.*', 'p.name as package_name', 'u.username as reseller_username')
            ->from($prefix . 'accounts', 'a')
            ->leftJoin('a', $prefix . 'packages', 'p', 'a.package_id = p.id')
            ->leftJoin('a', $prefix . 'users', 'u', 'a.reseller_id = u.id')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->orderBy('a.created_at', 'DESC');

        if ($search) {
            $queryBuilder->andWhere('a.username LIKE :search OR a.domain LIKE :search OR a.email LIKE :search')
                ->setParameter('search', "%{$search}%");
        }

        if ($status) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        // Get total count
        $countQuery = clone $queryBuilder;
        $total = (int) $countQuery->select('COUNT(*)')->executeQuery()->fetchOne();

        // Get results
        $accounts = $queryBuilder->executeQuery()->fetchAllAssociative();

        return $this->paginated($accounts, $total, $page, $perPage);
    }

    /**
     * Get a specific account
     *
     * GET /api/v1/accounts/{id}
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $account = $db->fetchAssociative(
            "SELECT a.*, p.name as package_name, u.username as reseller_username
             FROM {$prefix}accounts a
             LEFT JOIN {$prefix}packages p ON a.package_id = p.id
             LEFT JOIN {$prefix}users u ON a.reseller_id = u.id
             WHERE a.id = ?",
            [$id]
        );

        if (!$account) {
            return $this->notFound('Account not found');
        }

        return $this->success($account);
    }

    /**
     * Create a new account
     *
     * POST /api/v1/accounts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate input
        $errors = $this->validate($data, [
            'username' => 'required|min:3|max:16',
            'domain' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'package_id' => 'required|numeric',
        ]);

        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        try {
            $db->beginTransaction();

            // Check if username already exists
            $exists = $db->fetchOne(
                "SELECT COUNT(*) FROM {$prefix}accounts WHERE username = ?",
                [$data['username']]
            );

            if ($exists > 0) {
                return $this->error('Username already exists', 400);
            }

            // Check if domain already exists
            $exists = $db->fetchOne(
                "SELECT COUNT(*) FROM {$prefix}accounts WHERE domain = ?",
                [$data['domain']]
            );

            if ($exists > 0) {
                return $this->error('Domain already exists', 400);
            }

            // Get package details
            $package = $db->fetchAssociative(
                "SELECT * FROM {$prefix}packages WHERE id = ?",
                [$data['package_id']]
            );

            if (!$package) {
                return $this->error('Package not found', 404);
            }

            // Create account
            $accountData = [
                'username' => $data['username'],
                'domain' => $data['domain'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
                'package_id' => $data['package_id'],
                'home_directory' => '/home/' . $data['username'],
                'disk_quota' => $package['disk_quota'],
                'bandwidth_quota' => $package['bandwidth_quota'],
                'inodes_quota' => $package['inodes_quota'],
                'shell_access' => $package['shell_access'],
                'status' => 'active',
                'theme' => 'default',
                'locale' => 'en_US',
                'timezone' => 'UTC',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $db->insert($prefix . 'accounts', $accountData);
            $accountId = $db->lastInsertId();

            // Create main domain
            $db->insert($prefix . 'domains', [
                'account_id' => $accountId,
                'domain' => $data['domain'],
                'type' => 'main',
                'document_root' => '/home/' . $data['username'] . '/public_html',
                'ssl_enabled' => false,
                'autossl_enabled' => true,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $db->commit();

            // Log audit
            logger("Account created: {$data['username']}");

            return $this->created([
                'id' => $accountId,
                'username' => $data['username'],
                'domain' => $data['domain'],
            ]);
        } catch (\Throwable $e) {
            $db->rollBack();
            logger('Account creation error: ' . $e->getMessage());
            return $this->error('Failed to create account', 500);
        }
    }

    /**
     * Update an account
     *
     * PUT /api/v1/accounts/{id}
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        // Check if account exists
        $account = $db->fetchAssociative(
            "SELECT * FROM {$prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            return $this->notFound('Account not found');
        }

        $updateData = [];

        // Update allowed fields
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['contact_email'])) {
            $updateData['contact_email'] = $data['contact_email'];
        }

        if (isset($data['package_id'])) {
            $updateData['package_id'] = $data['package_id'];
        }

        if (isset($data['shell_access'])) {
            $updateData['shell_access'] = (bool) $data['shell_access'];
        }

        if (isset($data['theme'])) {
            $updateData['theme'] = $data['theme'];
        }

        if (empty($updateData)) {
            return $this->error('No fields to update', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $db->update($prefix . 'accounts', $updateData, ['id' => $id]);

        logger("Account updated: {$account['username']}");

        return $this->success(['message' => 'Account updated successfully']);
    }

    /**
     * Suspend an account
     *
     * POST /api/v1/accounts/{id}/suspend
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'Administrative action';

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $account = $db->fetchAssociative(
            "SELECT * FROM {$prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            return $this->notFound('Account not found');
        }

        $db->update($prefix . 'accounts', [
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        logger("Account suspended: {$account['username']}, Reason: {$reason}");

        return $this->success(['message' => 'Account suspended successfully']);
    }

    /**
     * Unsuspend an account
     *
     * POST /api/v1/accounts/{id}/unsuspend
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function unsuspend(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $account = $db->fetchAssociative(
            "SELECT * FROM {$prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            return $this->notFound('Account not found');
        }

        $db->update($prefix . 'accounts', [
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        logger("Account unsuspended: {$account['username']}");

        return $this->success(['message' => 'Account unsuspended successfully']);
    }

    /**
     * Delete an account
     *
     * DELETE /api/v1/accounts/{id}
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $account = $db->fetchAssociative(
            "SELECT * FROM {$prefix}accounts WHERE id = ?",
            [$id]
        );

        if (!$account) {
            return $this->notFound('Account not found');
        }

        // Soft delete
        $db->update($prefix . 'accounts', [
            'status' => 'terminated',
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        logger("Account deleted: {$account['username']}");

        return $this->noContent();
    }
}
