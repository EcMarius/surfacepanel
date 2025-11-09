<?php

namespace VirPanel\Core\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple Web Router
 *
 * Handles web routes with middleware support
 */
class WebRouter
{
    /**
     * Registered routes
     *
     * @var array
     */
    protected array $routes = [];

    /**
     * Global middleware
     *
     * @var array
     */
    protected array $middleware = [];

    /**
     * Add a GET route
     *
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return void
     */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add a POST route
     *
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return void
     */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add a route
     *
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @param array $middleware
     * @return void
     */
    protected function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Add global middleware
     *
     * @param string|object $middleware
     * @return void
     */
    public function addMiddleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Handle request
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertPathToRegex($route['path']);

            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove full match

                // Execute middleware
                $allMiddleware = array_merge($this->middleware, $route['middleware']);

                foreach ($allMiddleware as $middleware) {
                    $middlewareInstance = is_string($middleware) ? new $middleware() : $middleware;

                    $response = $middlewareInstance->handle($request, function($req) {
                        return null;
                    });

                    if ($response instanceof Response) {
                        return $response;
                    }
                }

                // Execute handler
                return $this->executeHandler($route['handler'], $request, $matches);
            }
        }

        // No route found
        return new Response('Not Found', 404);
    }

    /**
     * Convert path to regex pattern
     *
     * @param string $path
     * @return string
     */
    protected function convertPathToRegex(string $path): string
    {
        // Replace {param} with regex capture
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Execute route handler
     *
     * @param callable|array $handler
     * @param Request $request
     * @param array $params
     * @return Response
     */
    protected function executeHandler(callable|array $handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = call_user_func_array($handler, array_merge([$request], $params));
        } elseif (is_array($handler) && count($handler) === 2) {
            [$controller, $method] = $handler;
            $controllerInstance = is_string($controller) ? new $controller() : $controller;
            $result = call_user_func_array([$controllerInstance, $method], array_merge([$request], $params));
        } else {
            return new Response('Invalid handler', 500);
        }

        if ($result instanceof Response) {
            return $result;
        }

        return new Response((string) $result);
    }
}
