<?php

namespace VirPanel\Api\Controllers;

use VirPanel\Api\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Domain API Controller
 *
 * Handles domain management API endpoints
 */
class DomainController extends Controller
{
    /**
     * List all domains for an account
     *
     * GET /api/v1/accounts/{accountId}/domains
     *
     * @param Request $request
     * @param int $accountId
     * @return JsonResponse
     */
    public function index(Request $request, int $accountId): JsonResponse
    {
        $type = $request->query->get('type');

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $queryBuilder = $db->createQueryBuilder()
            ->select('*')
            ->from($prefix . 'domains')
            ->where('account_id = :account_id')
            ->setParameter('account_id', $accountId)
            ->orderBy('type', 'ASC')
            ->addOrderBy('domain', 'ASC');

        if ($type) {
            $queryBuilder->andWhere('type = :type')
                ->setParameter('type', $type);
        }

        $domains = $queryBuilder->executeQuery()->fetchAllAssociative();

        return $this->success($domains);
    }

    /**
     * Get a specific domain
     *
     * GET /api/v1/domains/{id}
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $domain = $db->fetchAssociative(
            "SELECT * FROM {$prefix}domains WHERE id = ?",
            [$id]
        );

        if (!$domain) {
            return $this->notFound('Domain not found');
        }

        return $this->success($domain);
    }

    /**
     * Add a domain (addon, parked, or subdomain)
     *
     * POST /api/v1/accounts/{accountId}/domains
     *
     * @param Request $request
     * @param int $accountId
     * @return JsonResponse
     */
    public function store(Request $request, int $accountId): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate input
        $errors = $this->validate($data, [
            'domain' => 'required',
            'type' => 'required|in:addon,parked,subdomain',
        ]);

        if (!empty($errors)) {
            return $this->validationError($errors);
        }

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        try {
            // Check if account exists
            $account = $db->fetchAssociative(
                "SELECT * FROM {$prefix}accounts WHERE id = ?",
                [$accountId]
            );

            if (!$account) {
                return $this->notFound('Account not found');
            }

            // Check if domain already exists
            $exists = $db->fetchOne(
                "SELECT COUNT(*) FROM {$prefix}domains WHERE domain = ?",
                [$data['domain']]
            );

            if ($exists > 0) {
                return $this->error('Domain already exists', 400);
            }

            // Determine document root
            $documentRoot = match ($data['type']) {
                'addon' => '/home/' . $account['username'] . '/' . $data['domain'],
                'parked' => '/home/' . $account['username'] . '/public_html',
                'subdomain' => '/home/' . $account['username'] . '/public_html/' . explode('.', $data['domain'])[0],
            };

            if (isset($data['document_root'])) {
                $documentRoot = $data['document_root'];
            }

            // Create domain
            $domainData = [
                'account_id' => $accountId,
                'domain' => $data['domain'],
                'type' => $data['type'],
                'document_root' => $documentRoot,
                'ssl_enabled' => false,
                'autossl_enabled' => true,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $db->insert($prefix . 'domains', $domainData);
            $domainId = $db->lastInsertId();

            logger("Domain created: {$data['domain']} for account {$account['username']}");

            return $this->created([
                'id' => $domainId,
                'domain' => $data['domain'],
                'type' => $data['type'],
            ]);
        } catch (\Throwable $e) {
            logger('Domain creation error: ' . $e->getMessage());
            return $this->error('Failed to create domain', 500);
        }
    }

    /**
     * Update a domain
     *
     * PUT /api/v1/domains/{id}
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

        $domain = $db->fetchAssociative(
            "SELECT * FROM {$prefix}domains WHERE id = ?",
            [$id]
        );

        if (!$domain) {
            return $this->notFound('Domain not found');
        }

        $updateData = [];

        if (isset($data['document_root'])) {
            $updateData['document_root'] = $data['document_root'];
        }

        if (isset($data['redirect_www'])) {
            $updateData['redirect_www'] = (bool) $data['redirect_www'];
        }

        if (isset($data['force_https'])) {
            $updateData['force_https'] = (bool) $data['force_https'];
        }

        if (isset($data['redirect_url'])) {
            $updateData['redirect_url'] = $data['redirect_url'];
            $updateData['redirect_code'] = $data['redirect_code'] ?? 301;
        }

        if (empty($updateData)) {
            return $this->error('No fields to update', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $db->update($prefix . 'domains', $updateData, ['id' => $id]);

        logger("Domain updated: {$domain['domain']}");

        return $this->success(['message' => 'Domain updated successfully']);
    }

    /**
     * Delete a domain
     *
     * DELETE /api/v1/domains/{id}
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $domain = $db->fetchAssociative(
            "SELECT * FROM {$prefix}domains WHERE id = ?",
            [$id]
        );

        if (!$domain) {
            return $this->notFound('Domain not found');
        }

        // Cannot delete main domain
        if ($domain['type'] === 'main') {
            return $this->error('Cannot delete main domain', 400);
        }

        $db->delete($prefix . 'domains', ['id' => $id]);

        logger("Domain deleted: {$domain['domain']}");

        return $this->noContent();
    }

    /**
     * Enable SSL for a domain
     *
     * POST /api/v1/domains/{id}/ssl/enable
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function enableSsl(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $domain = $db->fetchAssociative(
            "SELECT * FROM {$prefix}domains WHERE id = ?",
            [$id]
        );

        if (!$domain) {
            return $this->notFound('Domain not found');
        }

        // TODO: Actually install SSL certificate via Let's Encrypt or provided cert

        $db->update($prefix . 'domains', [
            'ssl_enabled' => true,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        logger("SSL enabled for domain: {$domain['domain']}");

        return $this->success(['message' => 'SSL enabled successfully']);
    }

    /**
     * Disable SSL for a domain
     *
     * POST /api/v1/domains/{id}/ssl/disable
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function disableSsl(Request $request, int $id): JsonResponse
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $domain = $db->fetchAssociative(
            "SELECT * FROM {$prefix}domains WHERE id = ?",
            [$id]
        );

        if (!$domain) {
            return $this->notFound('Domain not found');
        }

        $db->update($prefix . 'domains', [
            'ssl_enabled' => false,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        logger("SSL disabled for domain: {$domain['domain']}");

        return $this->success(['message' => 'SSL disabled successfully']);
    }
}
