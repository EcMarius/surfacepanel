<?php

namespace VirPanel\Api\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @return Response|null
     */
    public function handle(Request $request): ?Response
    {
        // If it's a preflight request
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest();
        }

        // For regular requests, we'll add headers in the response
        // This is handled by adding a response listener
        return null;
    }

    /**
     * Handle preflight request
     *
     * @return Response
     */
    protected function handlePreflightRequest(): Response
    {
        $response = new Response('', 200);

        $response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigins());
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours

        return $response;
    }

    /**
     * Get allowed origins
     *
     * @return string
     */
    protected function getAllowedOrigins(): string
    {
        $allowed = config('cors.allowed_origins', '*');

        if (is_array($allowed)) {
            return implode(', ', $allowed);
        }

        return $allowed;
    }

    /**
     * Add CORS headers to response
     *
     * @param Response $response
     * @return void
     */
    public static function addCorsHeaders(Response $response): void
    {
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            $allowed = config('cors.allowed_origins', '*');

            if (is_array($allowed)) {
                $allowed = implode(', ', $allowed);
            }

            $response->headers->set('Access-Control-Allow-Origin', $allowed);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }
}
