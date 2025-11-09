<?php

/**
 * Web Routes Configuration
 *
 * Define all web routes here
 */

use VirPanel\Core\Http\WebRouter;
use VirPanel\Core\Http\Controllers\AuthController;
use VirPanel\Core\Http\Controllers\AdminDashboardController;
use VirPanel\Core\Http\Controllers\AccountController;
use VirPanel\Core\Http\Controllers\PackageController;
use VirPanel\Core\Http\Controllers\CloudflareController;
use VirPanel\Core\Http\Controllers\FileManagerController;
use VirPanel\Core\Http\Controllers\ResellerController;
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

    // Account Management
    $router->get('/admin/accounts', [AccountController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->get('/admin/accounts/create', [AccountController::class, 'create'], [AuthenticateMiddleware::class]);
    $router->post('/admin/accounts', [AccountController::class, 'store'], [AuthenticateMiddleware::class]);
    $router->get('/admin/accounts/{id}', [AccountController::class, 'show'], [AuthenticateMiddleware::class]);
    $router->get('/admin/accounts/{id}/edit', [AccountController::class, 'edit'], [AuthenticateMiddleware::class]);
    $router->post('/admin/accounts/{id}', [AccountController::class, 'update'], [AuthenticateMiddleware::class]);
    $router->post('/admin/accounts/{id}/suspend', [AccountController::class, 'suspend'], [AuthenticateMiddleware::class]);
    $router->post('/admin/accounts/{id}/unsuspend', [AccountController::class, 'unsuspend'], [AuthenticateMiddleware::class]);

    // Package Management
    $router->get('/admin/packages', [PackageController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->get('/admin/packages/create', [PackageController::class, 'create'], [AuthenticateMiddleware::class]);
    $router->post('/admin/packages', [PackageController::class, 'store'], [AuthenticateMiddleware::class]);
    $router->get('/admin/packages/{id}', [PackageController::class, 'show'], [AuthenticateMiddleware::class]);
    $router->get('/admin/packages/{id}/edit', [PackageController::class, 'edit'], [AuthenticateMiddleware::class]);
    $router->post('/admin/packages/{id}', [PackageController::class, 'update'], [AuthenticateMiddleware::class]);

    // Cloudflare Integration
    $router->get('/admin/cloudflare', [CloudflareController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->get('/admin/cloudflare/create', [CloudflareController::class, 'create'], [AuthenticateMiddleware::class]);
    $router->post('/admin/cloudflare', [CloudflareController::class, 'store'], [AuthenticateMiddleware::class]);
    $router->get('/admin/cloudflare/{id}', [CloudflareController::class, 'show'], [AuthenticateMiddleware::class]);

    // File Manager
    $router->get('/admin/filemanager/{id}', [FileManagerController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/admin/filemanager/{id}/upload', [FileManagerController::class, 'upload'], [AuthenticateMiddleware::class]);
    $router->get('/admin/filemanager/{id}/download', [FileManagerController::class, 'download'], [AuthenticateMiddleware::class]);
    $router->post('/admin/filemanager/{id}/folder', [FileManagerController::class, 'createFolder'], [AuthenticateMiddleware::class]);
    $router->post('/admin/filemanager/{id}/delete', [FileManagerController::class, 'delete'], [AuthenticateMiddleware::class]);
    $router->post('/admin/filemanager/{id}/rename', [FileManagerController::class, 'rename'], [AuthenticateMiddleware::class]);
    $router->get('/admin/filemanager/{id}/edit', [FileManagerController::class, 'edit'], [AuthenticateMiddleware::class]);
    $router->post('/admin/filemanager/{id}/save', [FileManagerController::class, 'save'], [AuthenticateMiddleware::class]);
    $router->post('/admin/filemanager/{id}/chmod', [FileManagerController::class, 'chmod'], [AuthenticateMiddleware::class]);

    // Reseller Management
    $router->get('/admin/resellers', [ResellerController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->get('/admin/resellers/create', [ResellerController::class, 'create'], [AuthenticateMiddleware::class]);
    $router->post('/admin/resellers', [ResellerController::class, 'store'], [AuthenticateMiddleware::class]);
    $router->get('/admin/resellers/{id}', [ResellerController::class, 'show'], [AuthenticateMiddleware::class]);
    $router->get('/admin/resellers/{id}/edit', [ResellerController::class, 'edit'], [AuthenticateMiddleware::class]);
    $router->post('/admin/resellers/{id}', [ResellerController::class, 'update'], [AuthenticateMiddleware::class]);
    $router->post('/admin/resellers/{id}/suspend', [ResellerController::class, 'suspend'], [AuthenticateMiddleware::class]);
    $router->post('/admin/resellers/{id}/unsuspend', [ResellerController::class, 'unsuspend'], [AuthenticateMiddleware::class]);

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
