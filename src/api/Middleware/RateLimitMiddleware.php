<?php

namespace VirPanel\Api\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Rate Limit Middleware
 *
 * Implements rate limiting for API requests
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Maximum requests per window
     *
     * @var int
     */
    protected int $maxRequests;

    /**
     * Window duration in seconds
     *
     * @var int
     */
    protected int $windowSeconds;

    /**
     * Create a new rate limit middleware instance
     *
     * @param int $maxRequests
     * @param int $windowSeconds
     */
    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @return Response|null
     */
    public function handle(Request $request): ?Response
    {
        $key = $this->getRateLimitKey($request);

        $attempts = $this->getAttempts($key);
        $maxAttempts = $this->maxRequests;

        if ($attempts >= $maxAttempts) {
            $retryAfter = $this->getRetryAfter($key);

            return new JsonResponse([
                'success' => false,
                'error' => 'Too Many Requests',
                'message' => "Rate limit exceeded. Try again in {$retryAfter} seconds.",
                'retry_after' => $retryAfter,
            ], 429, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $this->incrementAttempts($key);

        // Add rate limit headers to be added to response
        $request->attributes->set('ratelimit_headers', [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $maxAttempts - $attempts - 1),
        ]);

        return null;
    }

    /**
     * Get the rate limit key for the request
     *
     * @param Request $request
     * @return string
     */
    protected function getRateLimitKey(Request $request): string
    {
        $user = $request->attributes->get('user');

        if ($user) {
            return 'ratelimit:user:' . $user['user_id'];
        }

        return 'ratelimit:ip:' . $request->getClientIp();
    }

    /**
     * Get the number of attempts
     *
     * @param string $key
     * @return int
     */
    protected function getAttempts(string $key): int
    {
        $cache = app('cache');
        return (int) $cache->get($key, 0);
    }

    /**
     * Increment attempts
     *
     * @param string $key
     * @return void
     */
    protected function incrementAttempts(string $key): void
    {
        $cache = app('cache');
        $attempts = $this->getAttempts($key);

        if ($attempts === 0) {
            $cache->set($key, 1, $this->windowSeconds);
        } else {
            $cache->set($key, $attempts + 1, $this->windowSeconds);
        }
    }

    /**
     * Get retry after time in seconds
     *
     * @param string $key
     * @return int
     */
    protected function getRetryAfter(string $key): int
    {
        $cache = app('cache');

        // Get TTL from cache
        // This is a simplified version; proper implementation would use cache TTL methods
        return $this->windowSeconds;
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response
     * @param Request $request
     * @return void
     */
    public static function addRateLimitHeaders(Response $response, Request $request): void
    {
        $headers = $request->attributes->get('ratelimit_headers', []);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }
    }
}
