<?php

namespace VirPanel\Core\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use VirPanel\Core\Application;
use VirPanel\Core\Template\TemplateEngine;

/**
 * API Documentation Controller
 *
 * Generates OpenAPI/Swagger documentation
 */
class ApiDocController extends ApiController
{
    private TemplateEngine $template;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->template = new TemplateEngine($app);
    }

    /**
     * Display interactive API documentation
     */
    public function index(Request $request): Response
    {
        return new Response($this->template->render('api/docs.html.twig', [
            'api_url' => config('app.url') . '/api/v1',
        ]));
    }

    /**
     * Get OpenAPI specification
     */
    public function openapi(Request $request): JsonResponse
    {
        $spec = $this->generateOpenApiSpec();
        return new JsonResponse($spec);
    }

    /**
     * Generate OpenAPI 3.0 specification
     */
    private function generateOpenApiSpec(): array
    {
        $baseUrl = config('app.url');

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'VirPanel API',
                'description' => 'Complete RESTful API for VirPanel - Web Hosting Control Panel',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'VirPanel Support',
                    'email' => 'support@virpanel.com',
                ],
            ],
            'servers' => [
                [
                    'url' => $baseUrl . '/api/v1',
                    'description' => 'Production Server',
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'API token authentication. Get your token from /admin/api/tokens',
                    ],
                ],
                'schemas' => $this->getSchemas(),
                'responses' => [
                    'UnauthorizedError' => [
                        'description' => 'API token is missing or invalid',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'Invalid or expired API token'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'RateLimitError' => [
                        'description' => 'Rate limit exceeded',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => false],
                                        'message' => ['type' => 'string', 'example' => 'Rate limit exceeded'],
                                        'retry_after' => ['type' => 'integer', 'example' => 60],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'paths' => $this->getPaths(),
            'tags' => [
                ['name' => 'Accounts', 'description' => 'Hosting account management'],
                ['name' => 'Packages', 'description' => 'Hosting package management'],
                ['name' => 'Domains', 'description' => 'Domain management (addon, subdomain, parked)'],
            ],
        ];
    }

    /**
     * Get API paths
     */
    private function getPaths(): array
    {
        return [
            '/accounts' => [
                'get' => [
                    'tags' => ['Accounts'],
                    'summary' => 'List all accounts',
                    'description' => 'Get a paginated list of all hosting accounts',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100]],
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['active', 'suspended']]],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/AccountList'],
                                ],
                            ],
                        ],
                        '401' => ['$ref' => '#/components/responses/UnauthorizedError'],
                        '429' => ['$ref' => '#/components/responses/RateLimitError'],
                    ],
                ],
                'post' => [
                    'tags' => ['Accounts'],
                    'summary' => 'Create new account',
                    'description' => 'Create a new hosting account',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreateAccount'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Account created successfully',
                        ],
                        '400' => [
                            'description' => 'Validation error',
                        ],
                    ],
                ],
            ],
            '/accounts/{id}' => [
                'get' => [
                    'tags' => ['Accounts'],
                    'summary' => 'Get account details',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Success'],
                        '404' => ['description' => 'Account not found'],
                    ],
                ],
                'put' => [
                    'tags' => ['Accounts'],
                    'summary' => 'Update account',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UpdateAccount'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Account updated successfully'],
                        '404' => ['description' => 'Account not found'],
                    ],
                ],
                'delete' => [
                    'tags' => ['Accounts'],
                    'summary' => 'Delete account',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Account deleted successfully'],
                        '404' => ['description' => 'Account not found'],
                    ],
                ],
            ],
            '/accounts/{id}/suspend' => [
                'post' => [
                    'tags' => ['Accounts'],
                    'summary' => 'Suspend account',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'reason' => ['type' => 'string', 'example' => 'Payment overdue'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Account suspended successfully'],
                    ],
                ],
            ],
            '/accounts/{id}/unsuspend' => [
                'post' => [
                    'tags' => ['Accounts'],
                    'summary' => 'Unsuspend account',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Account unsuspended successfully'],
                    ],
                ],
            ],
            '/packages' => [
                'get' => [
                    'tags' => ['Packages'],
                    'summary' => 'List all packages',
                    'responses' => [
                        '200' => ['description' => 'Success'],
                    ],
                ],
                'post' => [
                    'tags' => ['Packages'],
                    'summary' => 'Create new package',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreatePackage'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Package created successfully'],
                    ],
                ],
            ],
            '/packages/{id}' => [
                'get' => [
                    'tags' => ['Packages'],
                    'summary' => 'Get package details',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Success'],
                        '404' => ['description' => 'Package not found'],
                    ],
                ],
                'put' => [
                    'tags' => ['Packages'],
                    'summary' => 'Update package',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Package updated successfully'],
                    ],
                ],
                'delete' => [
                    'tags' => ['Packages'],
                    'summary' => 'Delete package',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Package deleted successfully'],
                        '400' => ['description' => 'Package is in use'],
                    ],
                ],
            ],
            '/accounts/{accountId}/domains' => [
                'get' => [
                    'tags' => ['Domains'],
                    'summary' => 'List all domains for account',
                    'parameters' => [
                        ['name' => 'accountId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Success'],
                        '404' => ['description' => 'Account not found'],
                    ],
                ],
            ],
            '/accounts/{accountId}/domains/addon' => [
                'post' => [
                    'tags' => ['Domains'],
                    'summary' => 'Create addon domain',
                    'parameters' => [
                        ['name' => 'accountId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CreateAddonDomain'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => ['description' => 'Addon domain created successfully'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get schema definitions
     */
    private function getSchemas(): array
    {
        return [
            'AccountList' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Account']],
                            'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                        ],
                    ],
                ],
            ],
            'Account' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'username' => ['type' => 'string'],
                    'domain' => ['type' => 'string'],
                    'user_email' => ['type' => 'string'],
                    'package_name' => ['type' => 'string'],
                    'disk_quota' => ['type' => 'integer', 'description' => 'Disk quota in MB'],
                    'bandwidth_quota' => ['type' => 'integer', 'description' => 'Bandwidth quota in MB'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'suspended']],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'CreateAccount' => [
                'type' => 'object',
                'required' => ['username', 'domain', 'password', 'email', 'package_id'],
                'properties' => [
                    'username' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 16],
                    'domain' => ['type' => 'string', 'format' => 'hostname'],
                    'password' => ['type' => 'string', 'minLength' => 8],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'package_id' => ['type' => 'integer'],
                ],
            ],
            'UpdateAccount' => [
                'type' => 'object',
                'properties' => [
                    'package_id' => ['type' => 'integer'],
                    'disk_quota' => ['type' => 'integer'],
                    'bandwidth_quota' => ['type' => 'integer'],
                ],
            ],
            'CreatePackage' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'disk_quota' => ['type' => 'integer', 'description' => 'Disk quota in MB (0 for unlimited)'],
                    'bandwidth_quota' => ['type' => 'integer', 'description' => 'Bandwidth quota in MB (0 for unlimited)'],
                    'email_accounts' => ['type' => 'integer', 'description' => 'Max email accounts (-1 for unlimited)'],
                    'databases' => ['type' => 'integer', 'description' => 'Max databases (-1 for unlimited)'],
                    'subdomains' => ['type' => 'integer'],
                    'parked_domains' => ['type' => 'integer'],
                    'addon_domains' => ['type' => 'integer'],
                    'ftp_accounts' => ['type' => 'integer'],
                ],
            ],
            'CreateAddonDomain' => [
                'type' => 'object',
                'required' => ['domain', 'subdomain'],
                'properties' => [
                    'domain' => ['type' => 'string', 'format' => 'hostname'],
                    'subdomain' => ['type' => 'string'],
                    'document_root' => ['type' => 'string'],
                ],
            ],
            'Pagination' => [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'current_page' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'from' => ['type' => 'integer'],
                    'to' => ['type' => 'integer'],
                ],
            ],
        ];
    }
}
