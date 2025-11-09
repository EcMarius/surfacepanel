<?php

namespace VirPanel\Core\Http\Middleware;

use VirPanel\Core\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Guest Middleware for Web Routes
 *
 * Ensures user is NOT authenticated (for login/register pages)
 */
class GuestMiddleware
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
        // If user is already authenticated, redirect to dashboard
        if (Auth::check()) {
            $user = Auth::user();

            // Redirect based on role
            if ($user->isRoot() || $user->getRole() === 'admin') {
                return new RedirectResponse('/admin/dashboard');
            } elseif ($user->isReseller()) {
                return new RedirectResponse('/reseller/dashboard');
            } else {
                return new RedirectResponse('/user/dashboard');
            }
        }

        // Continue to next middleware/handler
        return $next($request);
    }
}
