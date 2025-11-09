<?php

namespace VirPanel\Core;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Dotenv\Dotenv;

/**
 * VirPanel Core Application
 *
 * Main application bootstrap and dependency injection container
 */
class Application
{
    /**
     * The application version
     */
    const VERSION = '0.1.0';

    /**
     * The base path for the application
     *
     * @var string
     */
    protected string $basePath;

    /**
     * The application container
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * The event dispatcher
     *
     * @var EventDispatcher
     */
    protected EventDispatcher $events;

    /**
     * The application router
     *
     * @var Router|null
     */
    protected ?Router $router = null;

    /**
     * The loaded service providers
     *
     * @var array
     */
    protected array $serviceProviders = [];

    /**
     * The loaded modules
     *
     * @var array
     */
    protected array $modules = [];

    /**
     * Application booted state
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * Create a new application instance
     *
     * @param string|null $basePath
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->loadEnvironmentVariables();
        $this->registerConfiguredProviders();
    }

    /**
     * Set the base path for the application
     *
     * @param string $basePath
     * @return $this
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, '\/');
        return $this;
    }

    /**
     * Get the base path of the application
     *
     * @param string $path
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get the path to the configuration directory
     *
     * @param string $path
     * @return string
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get the path to the public directory
     *
     * @param string $path
     * @return string
     */
    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Get the path to the storage directory
     *
     * @param string $path
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Register the basic bindings into the container
     *
     * @return void
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->container = new ContainerBuilder();
        $this->container->set('app', $this);

        $this->events = new EventDispatcher();
        $this->container->set('events', $this->events);
    }

    /**
     * Load the environment variables
     *
     * @return void
     */
    protected function loadEnvironmentVariables(): void
    {
        $dotenv = Dotenv::createImmutable($this->basePath());

        if (file_exists($this->basePath('.env'))) {
            $dotenv->load();
        }
    }

    /**
     * Register all configured providers
     *
     * @return void
     */
    protected function registerConfiguredProviders(): void
    {
        $providers = config('app.providers', []);

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Register a service provider
     *
     * @param string|object $provider
     * @return object
     */
    public function register(string|object $provider): object
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        $this->serviceProviders[] = $provider;

        if ($this->booted && method_exists($provider, 'boot')) {
            $provider->boot();
        }

        return $provider;
    }

    /**
     * Boot the application's service providers
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }

        $this->bootModules();

        $this->booted = true;
    }

    /**
     * Boot all registered modules
     *
     * @return void
     */
    protected function bootModules(): void
    {
        $moduleManager = $this->container->get('modules');

        if ($moduleManager) {
            $this->modules = $moduleManager->boot();
        }
    }

    /**
     * Handle an incoming HTTP request
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        try {
            $this->boot();

            // Get the router and match the request
            $router = $this->container->get('router');
            $response = $router->handle($request);

            return $response;
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle an uncaught exception
     *
     * @param \Throwable $e
     * @return Response
     */
    protected function handleException(\Throwable $e): Response
    {
        // Log the exception
        if ($logger = $this->container->get('logger')) {
            $logger->error($e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Return error response
        $content = config('app.debug')
            ? $this->getDebugErrorContent($e)
            : $this->getProductionErrorContent($e);

        return new Response(
            $content,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Get debug error content
     *
     * @param \Throwable $e
     * @return string
     */
    protected function getDebugErrorContent(\Throwable $e): string
    {
        return json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace(),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get production error content
     *
     * @param \Throwable $e
     * @return string
     */
    protected function getProductionErrorContent(\Throwable $e): string
    {
        return json_encode([
            'error' => true,
            'message' => 'Internal Server Error',
        ]);
    }

    /**
     * Get the application container
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the event dispatcher
     *
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->events;
    }

    /**
     * Get the application version
     *
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Determine if the application is in debug mode
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return (bool) env('APP_DEBUG', false);
    }

    /**
     * Get the environment the application is running in
     *
     * @return string
     */
    public function environment(): string
    {
        return env('APP_ENV', 'production');
    }

    /**
     * Determine if the application is running in the console
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    /**
     * The global application instance
     *
     * @var Application|null
     */
    protected static ?Application $instance = null;

    /**
     * Set the global application instance
     *
     * @param Application|null $app
     * @return Application|null
     */
    public static function setInstance(?Application $app = null): ?Application
    {
        return static::$instance = $app;
    }

    /**
     * Get the global application instance
     *
     * @return Application|null
     */
    public static function getInstance(): ?Application
    {
        return static::$instance;
    }
}
