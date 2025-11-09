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
use VirPanel\Core\Http\Controllers\ModuleController;
use VirPanel\Core\Http\Controllers\TemplateController;
use VirPanel\Core\Http\Controllers\DomainController;
use VirPanel\Core\Http\Controllers\EmailController;
use VirPanel\Core\Http\Controllers\DatabaseController;
use VirPanel\Core\Http\Controllers\SSLController;
use VirPanel\Core\Http\Controllers\IPAddressController;
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

    // IP Address Management (Admin)
    $router->get('/admin/ip-addresses', [IPAddressController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/admin/ip-addresses', [IPAddressController::class, 'store'], [AuthenticateMiddleware::class]);
    $router->post('/admin/ip-addresses/{id}', [IPAddressController::class, 'update'], [AuthenticateMiddleware::class]);
    $router->post('/admin/ip-addresses/{id}/set-default', [IPAddressController::class, 'setDefault'], [AuthenticateMiddleware::class]);
    $router->post('/admin/ip-addresses/{id}/delete', [IPAddressController::class, 'delete'], [AuthenticateMiddleware::class]);
    $router->post('/admin/ip-addresses/{id}/ptr', [IPAddressController::class, 'updatePTR'], [AuthenticateMiddleware::class]);
    $router->post('/admin/ip-addresses/assign', [IPAddressController::class, 'assignToAccount'], [AuthenticateMiddleware::class]);
    $router->post('/admin/accounts/{id}/remove-ip', [IPAddressController::class, 'removeFromAccount'], [AuthenticateMiddleware::class]);

    // Module Management
    $router->get('/admin/modules', [ModuleController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/admin/modules/upload', [ModuleController::class, 'upload'], [AuthenticateMiddleware::class]);
    $router->post('/admin/modules/{name}/enable', [ModuleController::class, 'enable'], [AuthenticateMiddleware::class]);
    $router->post('/admin/modules/{name}/disable', [ModuleController::class, 'disable'], [AuthenticateMiddleware::class]);
    $router->post('/admin/modules/{name}/uninstall', [ModuleController::class, 'uninstall'], [AuthenticateMiddleware::class]);
    $router->get('/admin/modules/{name}/details', [ModuleController::class, 'details'], [AuthenticateMiddleware::class]);

    // Template Management (Admin)
    $router->get('/admin/templates', [TemplateController::class, 'adminIndex'], [AuthenticateMiddleware::class]);
    $router->post('/admin/templates/upload', [TemplateController::class, 'upload'], [AuthenticateMiddleware::class]);
    $router->post('/admin/templates/{name}/activate', [TemplateController::class, 'activate'], [AuthenticateMiddleware::class]);
    $router->post('/admin/templates/{name}/uninstall', [TemplateController::class, 'uninstall'], [AuthenticateMiddleware::class]);
    $router->get('/admin/templates/{name}/preview', [TemplateController::class, 'preview'], [AuthenticateMiddleware::class]);

    // Template Selection (User)
    $router->get('/user/templates', [TemplateController::class, 'userIndex'], [AuthenticateMiddleware::class]);
    $router->post('/user/templates/{name}/select', [TemplateController::class, 'setUserTemplate'], [AuthenticateMiddleware::class]);

    // Domain Management (User)
    $router->get('/user/domains', [DomainController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/addon', [DomainController::class, 'storeAddonDomain'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/subdomain', [DomainController::class, 'storeSubdomain'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/parked', [DomainController::class, 'storeParkedDomain'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/redirect', [DomainController::class, 'storeRedirect'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/addon/{id}/delete', [DomainController::class, 'deleteAddonDomain'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/subdomain/{id}/delete', [DomainController::class, 'deleteSubdomain'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/parked/{id}/delete', [DomainController::class, 'deleteParkedDomain'], [AuthenticateMiddleware::class]);
    $router->post('/user/domains/redirect/{id}/delete', [DomainController::class, 'deleteRedirect'], [AuthenticateMiddleware::class]);

    // Email Management (User)
    $router->get('/user/email', [EmailController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/accounts', [EmailController::class, 'storeAccount'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/accounts/{id}/password', [EmailController::class, 'updatePassword'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/accounts/{id}/quota', [EmailController::class, 'updateQuota'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/accounts/{id}/delete', [EmailController::class, 'deleteAccount'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/forwarders', [EmailController::class, 'storeForwarder'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/forwarders/{id}/delete', [EmailController::class, 'deleteForwarder'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/autoresponders', [EmailController::class, 'storeAutoresponder'], [AuthenticateMiddleware::class]);
    $router->post('/user/email/autoresponders/{id}/delete', [EmailController::class, 'deleteAutoresponder'], [AuthenticateMiddleware::class]);

    // Database Management (User)
    $router->get('/user/databases', [DatabaseController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/create', [DatabaseController::class, 'storeDatabase'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/{id}/delete', [DatabaseController::class, 'deleteDatabase'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/users/create', [DatabaseController::class, 'storeUser'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/users/{id}/password', [DatabaseController::class, 'updateUserPassword'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/users/{id}/delete', [DatabaseController::class, 'deleteUser'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/privileges/grant', [DatabaseController::class, 'addUserToDatabase'], [AuthenticateMiddleware::class]);
    $router->post('/user/databases/privileges/{id}/revoke', [DatabaseController::class, 'removeUserFromDatabase'], [AuthenticateMiddleware::class]);

    // SSL/TLS Management (User)
    $router->get('/user/ssl', [SSLController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/user/ssl/letsencrypt/issue', [SSLController::class, 'issueLetsEncrypt'], [AuthenticateMiddleware::class]);
    $router->post('/user/ssl/upload', [SSLController::class, 'uploadCertificate'], [AuthenticateMiddleware::class]);
    $router->post('/user/ssl/csr/generate', [SSLController::class, 'generateCSR'], [AuthenticateMiddleware::class]);
    $router->post('/user/ssl/{id}/renew', [SSLController::class, 'renewCertificate'], [AuthenticateMiddleware::class]);
    $router->post('/user/ssl/{id}/delete', [SSLController::class, 'deleteCertificate'], [AuthenticateMiddleware::class]);

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
