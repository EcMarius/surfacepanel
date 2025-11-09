<?php

namespace VirPanel\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API Router
 *
 * Handles routing for API requests
 */
class Router
{
    /**
     * Registered routes
     *
     * @var array
     */
    protected array $routes = [];

    /**
     * Middleware stack
     *
     * @var array
     */
    protected array $middleware = [];

    /**
     * Route group stack
     *
     * @var array
     */
    protected array $groupStack = [];

    /**
     * Add a GET route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Add a POST route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Add a PUT route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Add a PATCH route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Add a DELETE route
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Add a route that matches any HTTP method
     *
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function any(string $path, callable|array $handler): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler);
    }

    /**
     * Add a route
     *
     * @param string|array $methods
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function addRoute(string|array $methods, string $path, callable|array $handler): Route
    {
        $methods = is_array($methods) ? $methods : [$methods];

        // Apply group prefix and middleware
        if (!empty($this->groupStack)) {
            $group = end($this->groupStack);
            $path = ($group['prefix'] ?? '') . $path;

            if (isset($group['middleware'])) {
                $groupMiddleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
            }
        }

        $route = new Route($methods, $path, $handler);

        // Apply group middleware if any
        if (isset($groupMiddleware)) {
            foreach ($groupMiddleware as $middleware) {
                $route->middleware($middleware);
            }
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Create a route group
     *
     * @param array $attributes
     * @param callable $callback
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;

        call_user_func($callback, $this);

        array_pop($this->groupStack);
    }

    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        // Find matching route
        $route = $this->findRoute($method, $path);

        if (!$route) {
            return new JsonResponse([
                'error' => 'Route not found',
                'message' => "No route found for {$method} {$path}",
            ], 404);
        }

        // Execute middleware
        $response = $this->executeMiddleware($route, $request);

        if ($response instanceof Response) {
            return $response;
        }

        // Execute route handler
        try {
            $result = $this->executeHandler($route, $request);

            if ($result instanceof Response) {
                return $result;
            }

            return new JsonResponse($result);
        } catch (\Throwable $e) {
            logger('API Error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'Internal Server Error',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Find a route that matches the method and path
     *
     * @param string $method
     * @param string $path
     * @return Route|null
     */
    protected function findRoute(string $method, string $path): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Execute route middleware
     *
     * @param Route $route
     * @param Request $request
     * @return Response|null
     */
    protected function executeMiddleware(Route $route, Request $request): ?Response
    {
        foreach ($route->getMiddleware() as $middleware) {
            $instance = is_string($middleware) ? new $middleware() : $middleware;

            $response = $instance->handle($request);

            if ($response instanceof Response) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Execute route handler
     *
     * @param Route $route
     * @param Request $request
     * @return mixed
     */
    protected function executeHandler(Route $route, Request $request): mixed
    {
        $handler = $route->getHandler();
        $parameters = $route->getParameters();

        // If handler is a callable
        if (is_callable($handler)) {
            return call_user_func_array($handler, array_merge([$request], $parameters));
        }

        // If handler is [Controller::class, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$controller, $method] = $handler;

            $controllerInstance = is_string($controller) ? new $controller() : $controller;

            if (!method_exists($controllerInstance, $method)) {
                throw new \RuntimeException("Method {$method} not found in controller");
            }

            return call_user_func_array(
                [$controllerInstance, $method],
                array_merge([$request], $parameters)
            );
        }

        throw new \RuntimeException('Invalid route handler');
    }

    /**
     * Get all routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
