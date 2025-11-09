<?php

namespace VirPanel\Core\Http\Middleware;

use VirPanel\Core\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * CSRF Protection Middleware
 *
 * Verifies CSRF token for state-changing requests
 */
class CsrfMiddleware
{
    /**
     * HTTP methods that require CSRF verification
     *
     * @var array
     */
    protected array $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * URIs excluded from CSRF verification
     *
     * @var array
     */
    protected array $except = [
        '/api/*',
    ];

    /**
     * Handle the request
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Skip CSRF for excluded URIs
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Skip CSRF for methods that don't modify state
        if (!in_array($request->getMethod(), $this->methods)) {
            return $next($request);
        }

        // Get token from request
        $token = $request->request->get('_token')
            ?? $request->headers->get('X-CSRF-TOKEN')
            ?? $request->headers->get('X-XSRF-TOKEN');

        // Verify token
        if (!$token || !Auth::verifyCsrfToken($token)) {
            logger('CSRF token verification failed', [
                'ip' => $request->getClientIp(),
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
            ]);

            // Return appropriate error response
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'error' => 'CSRF token mismatch',
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                ], 419);
            }

            // Redirect back with error
            $_SESSION['error'] = 'Your session has expired. Please try again.';
            return new Response('CSRF token mismatch', 419);
        }

        // Continue to next middleware/handler
        return $next($request);
    }

    /**
     * Check if request should skip CSRF verification
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldSkip(Request $request): bool
    {
        $uri = $request->getPathInfo();

        foreach ($this->except as $pattern) {
            // Convert pattern to regex
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

            if (preg_match($regex, $uri)) {
                return true;
            }
        }

        return false;
    }
}
