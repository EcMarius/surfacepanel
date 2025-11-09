<?php

namespace VirPanel\Core\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use Doctrine\DBAL\Connection;

/**
 * API Authentication Middleware
 *
 * Validates API tokens and sets the authenticated user
 */
class ApiAuthMiddleware implements MiddlewareInterface
{
    private Connection $db;
    private string $prefix;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->prefix = config('database.prefix', 'vp_');
    }

    public function handle(Request $request, callable $next): Response
    {
        // Get token from Authorization header or query parameter
        $token = $this->extractToken($request);

        if (!$token) {
            return new JsonResponse([
                'success' => false,
                'message' => 'API token is required',
            ], 401);
        }

        // Validate token
        $apiToken = $this->validateToken($token);

        if (!$apiToken) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid or expired API token',
            ], 401);
        }

        // Update last used timestamp
        $this->updateLastUsed($apiToken['id']);

        // Set authenticated user in request attributes
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('api_user_id', $apiToken['user_id']);
        $request->attributes->set('api_permissions', json_decode($apiToken['permissions'] ?? '[]', true));

        return $next($request);
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        // Check Authorization header (Bearer token)
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check X-API-Token header
        $apiToken = $request->headers->get('X-API-Token');
        if ($apiToken) {
            return $apiToken;
        }

        // Check query parameter (less secure, but convenient for testing)
        $queryToken = $request->query->get('api_token');
        if ($queryToken) {
            return $queryToken;
        }

        return null;
    }

    /**
     * Validate token and return token data
     */
    private function validateToken(string $token): ?array
    {
        $apiToken = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}api_tokens
             WHERE token = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
            [$token]
        );

        return $apiToken ?: null;
    }

    /**
     * Update last used timestamp
     */
    private function updateLastUsed(int $tokenId): void
    {
        $this->db->update($this->prefix . 'api_tokens', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ], ['id' => $tokenId]);
    }
}
