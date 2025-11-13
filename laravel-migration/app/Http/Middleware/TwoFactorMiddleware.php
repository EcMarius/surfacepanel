<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user is not authenticated, let them through
        // (auth middleware should handle this)
        if (!$user) {
            return $next($request);
        }

        // Check if user has 2FA enabled
        if (!$user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // Check if 2FA is already verified for this session
        if ($request->session()->get('two_factor_verified', false)) {
            return $next($request);
        }

        // Check if the current route is the 2FA challenge route
        // Allow access to 2FA routes and logout route
        $allowedRoutes = [
            'two-factor.challenge',
            'two-factor.verify',
            'two-factor.backup',
            'two-factor.recovery',
            'logout',
        ];

        if (in_array($request->route()->getName(), $allowedRoutes)) {
            return $next($request);
        }

        // Redirect to 2FA challenge page
        return redirect()->route('two-factor.challenge');
    }
}
