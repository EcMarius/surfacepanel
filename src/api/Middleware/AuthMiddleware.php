<?php

namespace VirPanel\Api\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Authentication Middleware
 *
 * Verifies API authentication
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @return Response|null
     */
    public function handle(Request $request): ?Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'No authentication token provided',
            ], 401);
        }

        // Verify token
        $user = $this->verifyToken($token);

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or expired token',
            ], 401);
        }

        // Attach user to request
        $request->attributes->set('user', $user);
        $request->attributes->set('authenticated', true);

        return null; // Continue to next middleware/handler
    }

    /**
     * Extract token from request
     *
     * @param Request $request
     * @return string|null
     */
    protected function extractToken(Request $request): ?string
    {
        // Try Authorization header first
        $authHeader = $request->headers->get('Authorization');

        if ($authHeader && preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        // Try query parameter
        if ($request->query->has('token')) {
            return $request->query->get('token');
        }

        // Try cookie
        if ($request->cookies->has('api_token')) {
            return $request->cookies->get('api_token');
        }

        return null;
    }

    /**
     * Verify authentication token
     *
     * @param string $token
     * @return array|null
     */
    protected function verifyToken(string $token): ?array
    {
        try {
            // TODO: Implement proper JWT verification
            // For now, we'll do a database lookup

            $db = app('database');
            $prefix = config('database.prefix', 'vp_');

            $result = $db->fetchAssociative(
                "SELECT t.*, u.id as user_id, u.username, u.email, u.role, a.id as account_id
                 FROM {$prefix}api_tokens t
                 LEFT JOIN {$prefix}users u ON t.user_id = u.id
                 LEFT JOIN {$prefix}accounts a ON t.account_id = a.id
                 WHERE t.token = ?
                 AND (t.expires_at IS NULL OR t.expires_at > NOW())",
                [$token]
            );

            if (!$result) {
                return null;
            }

            // Check IP whitelist if set
            if (!empty($result['ip_whitelist'])) {
                $allowedIps = explode(',', $result['ip_whitelist']);
                $requestIp = request()->getClientIp();

                if (!in_array($requestIp, $allowedIps)) {
                    logger("API token used from unauthorized IP: {$requestIp}");
                    return null;
                }
            }

            // Update last used timestamp
            $db->update(
                $prefix . 'api_tokens',
                ['last_used_at' => date('Y-m-d H:i:s')],
                ['id' => $result['id']]
            );

            return [
                'token_id' => $result['id'],
                'user_id' => $result['user_id'],
                'account_id' => $result['account_id'],
                'username' => $result['username'],
                'email' => $result['email'],
                'role' => $result['role'],
                'scopes' => json_decode($result['scopes'] ?? '[]', true),
            ];
        } catch (\Throwable $e) {
            logger('Token verification error: ' . $e->getMessage());
            return null;
        }
    }
}
