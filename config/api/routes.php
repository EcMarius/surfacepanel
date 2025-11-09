<?php

use VirPanel\Api\Router;
use VirPanel\Api\Controllers\AccountController;
use VirPanel\Api\Controllers\DomainController;
use VirPanel\Api\Controllers\SystemController;
use VirPanel\Api\Middleware\AuthMiddleware;
use VirPanel\Api\Middleware\CorsMiddleware;
use VirPanel\Api\Middleware\RateLimitMiddleware;

/**
 * API Routes Configuration
 *
 * Define all API routes here
 */
return function (Router $router) {
    // Apply CORS middleware to all routes
    $router->group(['middleware' => CorsMiddleware::class], function ($router) {

        // Public routes (no authentication required)
        $router->group(['prefix' => '/api/v1'], function ($router) {

            // System info (public)
            $router->get('/info', [SystemController::class, 'info'])
                ->name('api.info');

            // Health check (public)
            $router->get('/health', [SystemController::class, 'health'])
                ->name('api.health');
        });

        // Protected routes (authentication required)
        $router->group([
            'prefix' => '/api/v1',
            'middleware' => [AuthMiddleware::class, new RateLimitMiddleware(100, 60)]
        ], function ($router) {

            // System routes
            $router->get('/system/stats', [SystemController::class, 'stats'])
                ->name('api.system.stats');

            $router->get('/system/packages', [SystemController::class, 'packages'])
                ->name('api.system.packages');

            $router->get('/system/settings', [SystemController::class, 'settings'])
                ->name('api.system.settings');

            $router->put('/system/settings/{key}', [SystemController::class, 'updateSetting'])
                ->name('api.system.settings.update');

            $router->get('/system/license', [SystemController::class, 'license'])
                ->name('api.system.license');

            // Account routes
            $router->get('/accounts', [AccountController::class, 'index'])
                ->name('api.accounts.index');

            $router->get('/accounts/{id}', [AccountController::class, 'show'])
                ->name('api.accounts.show');

            $router->post('/accounts', [AccountController::class, 'store'])
                ->name('api.accounts.store');

            $router->put('/accounts/{id}', [AccountController::class, 'update'])
                ->name('api.accounts.update');

            $router->delete('/accounts/{id}', [AccountController::class, 'destroy'])
                ->name('api.accounts.destroy');

            $router->post('/accounts/{id}/suspend', [AccountController::class, 'suspend'])
                ->name('api.accounts.suspend');

            $router->post('/accounts/{id}/unsuspend', [AccountController::class, 'unsuspend'])
                ->name('api.accounts.unsuspend');

            // Domain routes (nested under accounts)
            $router->get('/accounts/{accountId}/domains', [DomainController::class, 'index'])
                ->name('api.accounts.domains.index');

            $router->post('/accounts/{accountId}/domains', [DomainController::class, 'store'])
                ->name('api.accounts.domains.store');

            // Domain routes (direct access)
            $router->get('/domains/{id}', [DomainController::class, 'show'])
                ->name('api.domains.show');

            $router->put('/domains/{id}', [DomainController::class, 'update'])
                ->name('api.domains.update');

            $router->delete('/domains/{id}', [DomainController::class, 'destroy'])
                ->name('api.domains.destroy');

            $router->post('/domains/{id}/ssl/enable', [DomainController::class, 'enableSsl'])
                ->name('api.domains.ssl.enable');

            $router->post('/domains/{id}/ssl/disable', [DomainController::class, 'disableSsl'])
                ->name('api.domains.ssl.disable');

            // TODO: Add more routes for:
            // - Email accounts
            // - Databases
            // - FTP accounts
            // - DNS zones and records
            // - SSL certificates
            // - Backups
            // - Cron jobs
            // - File manager
            // - User management
            // - Reseller management
            // - Modules
            // - Templates
        });
    });
};
