<?php

namespace VirPanel\Core\Http\Middleware;

use VirPanel\Core\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Authentication Middleware for Web Routes
 *
 * Ensures user is authenticated before accessing protected routes
 */
class AuthenticateMiddleware
{
    /**
     * Handle the request
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            // Store intended URL
            $_SESSION['intended_url'] = $request->getRequestUri();

            // Redirect to login
            return new RedirectResponse('/login');
        }

        // Continue to next middleware/handler
        return $next($request);
    }
}
