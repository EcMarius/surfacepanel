<?php

namespace VirPanel\Modules\Example;

use VirPanel\Core\Application;
use VirPanel\Core\Contracts\ModuleInterface;

/**
 * Example Module
 *
 * This is a template/example module showing how to create modules
 */
class ExampleModule implements ModuleInterface
{
    /**
     * The application instance
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Create a new module instance
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register the module
     *
     * @return void
     */
    public function register(): void
    {
        // Register module services, routes, etc.
        logger("ExampleModule: Registering module");

        // Register routes
        $this->registerRoutes();

        // Register event listeners
        $this->registerListeners();
    }

    /**
     * Boot the module
     *
     * @return void
     */
    public function boot(): void
    {
        // Boot module logic
        logger("ExampleModule: Booting module");
    }

    /**
     * Register module routes
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        // Example: Register API routes
        // $router = $this->app->getContainer()->get('router');
        // $router->addRoute('GET', '/api/example', [ExampleController::class, 'index']);
    }

    /**
     * Register event listeners
     *
     * @return void
     */
    protected function registerListeners(): void
    {
        // Example: Listen to events
        // $events = $this->app->getEventDispatcher();
        // $events->addListener('user.created', [ExampleListener::class, 'handle']);
    }

    /**
     * Get module name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Example Module';
    }

    /**
     * Get module version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get module description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'An example module showing how to create modules for VirPanel';
    }
}
