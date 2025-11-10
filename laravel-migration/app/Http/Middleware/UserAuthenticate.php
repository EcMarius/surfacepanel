<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * Ensures only regular users can access the user panel
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('user')->check()) {
            return redirect()->route('user.login');
        }

        $user = Auth::guard('user')->user();

        // Only 'user' role can access user panel
        // Admins should use WHM panel
        if ($user->role !== 'user') {
            Auth::guard('user')->logout();
            $whmPort = config('virpanel.whm_port', 15443);
            return redirect("https://{$request->getHost()}:{$whmPort}/admin/login")
                ->with('info', 'Please use the WHM admin panel for your role.');
        }

        // Check if user's account is active
        if ($user->status !== 'active') {
            Auth::guard('user')->logout();
            return redirect()->route('user.login')
                ->with('error', 'Your account has been suspended or terminated.');
        }

        return $next($request);
    }
}
