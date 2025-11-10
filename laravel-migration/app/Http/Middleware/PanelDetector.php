<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class PanelDetector
{
    /**
     * Handle an incoming request.
     *
     * Detects which panel is being accessed based on the port number:
     * - Port 15443: WHM Admin Panel
     * - Port 15444: User Panel
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $port = $request->server('SERVER_PORT');
        $whmPort = config('virpanel.whm_port', 15443);
        $userPort = config('virpanel.user_port', 15444);

        if ($port == $whmPort) {
            // WHM Admin Panel
            Config::set('virpanel.active_panel', 'admin');
            Auth::shouldUse('admin');

            // Set view namespace for admin panel
            view()->addNamespace('panel', resource_path('views/admin'));

        } elseif ($port == $userPort) {
            // User Panel
            Config::set('virpanel.active_panel', 'user');
            Auth::shouldUse('user');

            // Set view namespace for user panel
            view()->addNamespace('panel', resource_path('views/user'));
        }

        // Share panel info with all views
        view()->share('activePanel', config('virpanel.active_panel'));
        view()->share('whmPort', $whmPort);
        view()->share('userPort', $userPort);

        return $next($request);
    }
}
