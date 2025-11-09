<?php

namespace VirPanel\Core\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

/**
 * API Token Controller
 *
 * Manages API token creation, listing, and revocation
 */
class ApiTokenController extends ApiController
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
     * List user's API tokens
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $tokens = $this->db->fetchAllAssociative(
                "SELECT id, name, token, permissions, expires_at, last_used_at, status, created_at
                 FROM {$this->prefix}api_tokens
                 WHERE user_id = ?
                 ORDER BY created_at DESC",
                [$user->getId()]
            );

            // Parse permissions JSON
            foreach ($tokens as &$token) {
                $token['permissions'] = json_decode($token['permissions'] ?? '[]', true);
            }

            return $this->success($tokens);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create new API token
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $name = trim($request->request->get('name', ''));
            $permissions = $request->request->get('permissions', []);
            $expiresInDays = (int)$request->request->get('expires_in_days', 0);

            // Validation
            $errors = $this->validateRequired(['name' => $name], ['name']);
            if (!empty($errors)) {
                return $this->error('Validation failed', 400, $errors);
            }

            // Generate unique token
            $token = $this->generateToken();

            // Calculate expiration
            $expiresAt = null;
            if ($expiresInDays > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));
            }

            // Store token
            $this->db->insert($this->prefix . 'api_tokens', [
                'user_id' => $user->getId(),
                'name' => $name,
                'token' => $token,
                'permissions' => json_encode($permissions),
                'expires_at' => $expiresAt,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $tokenId = $this->db->lastInsertId();

            return $this->success([
                'id' => $tokenId,
                'name' => $name,
                'token' => $token,
                'permissions' => $permissions,
                'expires_at' => $expiresAt,
            ], 'API token created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Revoke API token
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check token exists and belongs to user
            $token = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}api_tokens WHERE id = ? AND user_id = ?",
                [$id, $user->getId()]
            );

            if (!$token) {
                return $this->error('Token not found', 404);
            }

            // Revoke token
            $this->db->update($this->prefix . 'api_tokens', [
                'status' => 'revoked',
            ], ['id' => $id]);

            return $this->success(null, 'Token revoked successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete API token
     */
    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check token exists and belongs to user
            $token = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}api_tokens WHERE id = ? AND user_id = ?",
                [$id, $user->getId()]
            );

            if (!$token) {
                return $this->error('Token not found', 404);
            }

            // Delete token
            $this->db->delete($this->prefix . 'api_tokens', ['id' => $id]);

            return $this->success(null, 'Token deleted successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update token permissions
     */
    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $permissions = $request->request->get('permissions', []);

            // Check token exists and belongs to user
            $token = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}api_tokens WHERE id = ? AND user_id = ?",
                [$id, $user->getId()]
            );

            if (!$token) {
                return $this->error('Token not found', 404);
            }

            // Update permissions
            $this->db->update($this->prefix . 'api_tokens', [
                'permissions' => json_encode($permissions),
            ], ['id' => $id]);

            return $this->success(null, 'Token permissions updated successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Generate secure random token
     */
    private function generateToken(): string
    {
        return 'vp_' . bin2hex(random_bytes(32));
    }
}
