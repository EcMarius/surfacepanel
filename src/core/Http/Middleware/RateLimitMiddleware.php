<?php

namespace VirPanel\Core\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use Doctrine\DBAL\Connection;

/**
 * Rate Limit Middleware
 *
 * Implements token bucket algorithm for rate limiting API requests
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private Connection $db;
    private string $prefix;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->prefix = config('database.prefix', 'vp_');
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Get identifier (API token or IP address)
        $identifier = $this->getIdentifier($request);

        // Check rate limit
        $remaining = $this->checkRateLimit($identifier);

        if ($remaining < 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $this->windowSeconds,
            ], 429);
        }

        // Process request
        $response = $next($request);

        // Add rate limit headers
        if ($response instanceof JsonResponse) {
            $response->headers->set('X-RateLimit-Limit', (string)$this->maxRequests);
            $response->headers->set('X-RateLimit-Remaining', (string)max(0, $remaining));
            $response->headers->set('X-RateLimit-Reset', (string)(time() + $this->windowSeconds));
        }

        return $response;
    }

    /**
     * Get identifier for rate limiting
     */
    private function getIdentifier(Request $request): string
    {
        // Use API token if available
        $apiToken = $request->attributes->get('api_token');
        if ($apiToken && isset($apiToken['token'])) {
            return 'token:' . $apiToken['token'];
        }

        // Fall back to IP address
        return 'ip:' . $request->getClientIp();
    }

    /**
     * Check rate limit using token bucket algorithm
     */
    private function checkRateLimit(string $identifier): int
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Get or create rate limit entry
        $rateLimit = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}rate_limits WHERE identifier = ?",
            [$identifier]
        );

        if (!$rateLimit) {
            // Create new entry
            $this->db->insert($this->prefix . 'rate_limits', [
                'identifier' => $identifier,
                'requests' => 1,
                'window_start' => date('Y-m-d H:i:s', $now),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->maxRequests - 1;
        }

        // Check if window has expired
        $windowStartTime = strtotime($rateLimit['window_start']);
        if ($windowStartTime < $windowStart) {
            // Reset window
            $this->db->update($this->prefix . 'rate_limits', [
                'requests' => 1,
                'window_start' => date('Y-m-d H:i:s', $now),
            ], ['id' => $rateLimit['id']]);

            return $this->maxRequests - 1;
        }

        // Increment request count
        $newCount = $rateLimit['requests'] + 1;
        $this->db->update($this->prefix . 'rate_limits', [
            'requests' => $newCount,
        ], ['id' => $rateLimit['id']]);

        return $this->maxRequests - $newCount;
    }
}
