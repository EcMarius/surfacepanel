<?php

/**
 * Web Routes Configuration
 *
 * Define all web routes here
 */

use VirPanel\Core\Http\WebRouter;
use VirPanel\Core\Http\Controllers\AuthController;
use VirPanel\Core\Http\Controllers\AdminDashboardController;
use VirPanel\Core\Http\Middleware\GuestMiddleware;
use VirPanel\Core\Http\Middleware\AuthenticateMiddleware;
use VirPanel\Core\Http\Middleware\CsrfMiddleware;

return function (WebRouter $router) {
    // Apply CSRF middleware globally
    $router->addMiddleware(new CsrfMiddleware());

    // Guest routes (login, register) - redirect if authenticated
    $router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'login'], [GuestMiddleware::class]);

    $router->get('/password/reset', [AuthController::class, 'showPasswordReset'], [GuestMiddleware::class]);
    $router->post('/password/reset', [AuthController::class, 'sendPasswordResetLink'], [GuestMiddleware::class]);

    // Logout route (no guest middleware, requires auth)
    $router->get('/logout', [AuthController::class, 'logout']);
    $router->post('/logout', [AuthController::class, 'logout']);

    // Protected routes (require authentication)
    $router->get('/password/change', [AuthController::class, 'showPasswordChange'], [AuthenticateMiddleware::class]);
    $router->post('/password/change', [AuthController::class, 'changePassword'], [AuthenticateMiddleware::class]);

    // Admin dashboard
    $router->get('/admin/dashboard', [AdminDashboardController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->get('/admin', [AdminDashboardController::class, 'index'], [AuthenticateMiddleware::class]);

    // Redirect root to appropriate dashboard
    $router->get('/', function($request) {
        if (!\VirPanel\Core\Auth\Auth::check()) {
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/login');
        }

        $user = \VirPanel\Core\Auth\Auth::user();

        if ($user->isRoot() || $user->getRole() === 'admin') {
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/dashboard');
        } elseif ($user->isReseller()) {
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/reseller/dashboard');
        } else {
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/user/dashboard');
        }
    });

    // TODO: Add more routes:
    // - /admin/accounts - Account management
    // - /admin/packages - Package management
    // - /admin/resellers - Reseller management
    // - /admin/settings - System settings
    // - /user/dashboard - User cPanel dashboard
    // - /user/domains - Domain management
    // - /user/email - Email management
    // - /user/databases - Database management
    // - /user/files - File manager
    // - /reseller/dashboard - Reseller dashboard
};
