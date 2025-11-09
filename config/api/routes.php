<?php

/**
 * API Routes Configuration
 *
 * Define all API routes here
 * All routes are prefixed with /api/v1
 */

use VirPanel\Core\Http\WebRouter;
use VirPanel\Core\Http\Controllers\Api\ApiTokenController;
use VirPanel\Core\Http\Controllers\Api\AccountApiController;
use VirPanel\Core\Http\Controllers\Api\PackageApiController;
use VirPanel\Core\Http\Controllers\Api\DomainApiController;
use VirPanel\Core\Http\Controllers\Api\ApiDocController;
use VirPanel\Core\Http\Middleware\ApiAuthMiddleware;
use VirPanel\Core\Http\Middleware\RateLimitMiddleware;
use VirPanel\Core\Http\Middleware\AuthenticateMiddleware;

return function (WebRouter $router) {
    // Apply rate limiting to all API routes (100 requests per minute)
    $router->addMiddleware(new RateLimitMiddleware(100, 60));

    // API Documentation (public)
    $router->get('/api/v1/docs', [ApiDocController::class, 'index']);
    $router->get('/api/v1/openapi.json', [ApiDocController::class, 'openapi']);

    // Public API information endpoint
    $router->get('/api/v1', function($request) {
        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'name' => 'VirPanel API',
            'version' => '1.0.0',
            'documentation' => config('app.url') . '/api/v1/docs',
            'endpoints' => [
                'authentication' => '/api/v1/auth/tokens',
                'accounts' => '/api/v1/accounts',
                'packages' => '/api/v1/packages',
                'domains' => '/api/v1/accounts/{accountId}/domains',
            ],
            'auth' => [
                'type' => 'Bearer Token',
                'header' => 'Authorization: Bearer {token}',
                'alternative' => 'X-API-Token: {token}',
            ],
            'rate_limit' => [
                'limit' => 100,
                'window' => '1 minute',
            ],
        ]);
    });

    // API Token Management (requires web authentication)
    $router->get('/api/v1/auth/tokens', [ApiTokenController::class, 'index'], [AuthenticateMiddleware::class]);
    $router->post('/api/v1/auth/tokens', [ApiTokenController::class, 'store'], [AuthenticateMiddleware::class]);
    $router->post('/api/v1/auth/tokens/{id}/revoke', [ApiTokenController::class, 'revoke'], [AuthenticateMiddleware::class]);
    $router->delete('/api/v1/auth/tokens/{id}', [ApiTokenController::class, 'delete'], [AuthenticateMiddleware::class]);
    $router->put('/api/v1/auth/tokens/{id}/permissions', [ApiTokenController::class, 'updatePermissions'], [AuthenticateMiddleware::class]);

    // Protected API Routes (require API token authentication)
    $apiAuth = [ApiAuthMiddleware::class];

    // Accounts Management
    $router->get('/api/v1/accounts', [AccountApiController::class, 'index'], $apiAuth);
    $router->get('/api/v1/accounts/{id}', [AccountApiController::class, 'show'], $apiAuth);
    $router->post('/api/v1/accounts', [AccountApiController::class, 'store'], $apiAuth);
    $router->put('/api/v1/accounts/{id}', [AccountApiController::class, 'update'], $apiAuth);
    $router->delete('/api/v1/accounts/{id}', [AccountApiController::class, 'destroy'], $apiAuth);
    $router->post('/api/v1/accounts/{id}/suspend', [AccountApiController::class, 'suspend'], $apiAuth);
    $router->post('/api/v1/accounts/{id}/unsuspend', [AccountApiController::class, 'unsuspend'], $apiAuth);

    // Package Management
    $router->get('/api/v1/packages', [PackageApiController::class, 'index'], $apiAuth);
    $router->get('/api/v1/packages/{id}', [PackageApiController::class, 'show'], $apiAuth);
    $router->post('/api/v1/packages', [PackageApiController::class, 'store'], $apiAuth);
    $router->put('/api/v1/packages/{id}', [PackageApiController::class, 'update'], $apiAuth);
    $router->delete('/api/v1/packages/{id}', [PackageApiController::class, 'destroy'], $apiAuth);

    // Domain Management
    $router->get('/api/v1/accounts/{accountId}/domains', [DomainApiController::class, 'index'], $apiAuth);
    $router->post('/api/v1/accounts/{accountId}/domains/addon', [DomainApiController::class, 'storeAddon'], $apiAuth);
    $router->post('/api/v1/accounts/{accountId}/domains/subdomain', [DomainApiController::class, 'storeSubdomain'], $apiAuth);
    $router->post('/api/v1/accounts/{accountId}/domains/parked', [DomainApiController::class, 'storeParked'], $apiAuth);
    $router->delete('/api/v1/domains/addon/{id}', [DomainApiController::class, 'deleteAddon'], $apiAuth);
    $router->delete('/api/v1/domains/subdomain/{id}', [DomainApiController::class, 'deleteSubdomain'], $apiAuth);
    $router->delete('/api/v1/domains/parked/{id}', [DomainApiController::class, 'deleteParked'], $apiAuth);
};
