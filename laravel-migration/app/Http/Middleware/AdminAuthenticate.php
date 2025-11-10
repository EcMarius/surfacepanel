<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * Ensures only users with admin/root roles can access WHM
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::guard('admin')->user();

        // Only root, admin, or reseller roles can access WHM
        if (!in_array($user->role, ['root', 'admin', 'reseller'])) {
            Auth::guard('admin')->logout();
            return redirect()->route('admin.login')
                ->with('error', 'Unauthorized access. Admin privileges required.');
        }

        return $next($request);
    }
}
