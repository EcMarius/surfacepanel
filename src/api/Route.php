<?php

namespace VirPanel\Api;

/**
 * API Route
 *
 * Represents a single API route
 */
class Route
{
    /**
     * HTTP methods
     *
     * @var array
     */
    protected array $methods;

    /**
     * Route path
     *
     * @var string
     */
    protected string $path;

    /**
     * Route handler
     *
     * @var callable|array
     */
    protected $handler;

    /**
     * Route middleware
     *
     * @var array
     */
    protected array $middleware = [];

    /**
     * Route parameters
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Route name
     *
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * Create a new route instance
     *
     * @param array $methods
     * @param string $path
     * @param callable|array $handler
     */
    public function __construct(array $methods, string $path, callable|array $handler)
    {
        $this->methods = array_map('strtoupper', $methods);
        $this->path = $path;
        $this->handler = $handler;
    }

    /**
     * Add middleware to the route
     *
     * @param string|array $middleware
     * @return $this
     */
    public function middleware(string|array $middleware): static
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Set route name
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Check if route matches the given method and path
     *
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function matches(string $method, string $path): bool
    {
        // Check method
        if (!in_array(strtoupper($method), $this->methods)) {
            return false;
        }

        // Convert route path to regex
        $pattern = $this->getPattern();

        // Match path
        if (preg_match($pattern, $path, $matches)) {
            // Extract parameters
            array_shift($matches); // Remove full match
            $this->parameters = $matches;
            return true;
        }

        return false;
    }

    /**
     * Get regex pattern for the route
     *
     * @return string
     */
    protected function getPattern(): string
    {
        $pattern = $this->path;

        // Replace {param} with named capture groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);

        // Replace {param?} (optional) with optional named capture groups
        $pattern = preg_replace('/\{(\w+)\?\}/', '(?P<$1>[^/]+)?', $pattern);

        return '#^' . $pattern . '$#';
    }

    /**
     * Get route methods
     *
     * @return array
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get route path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get route handler
     *
     * @return callable|array
     */
    public function getHandler(): callable|array
    {
        return $this->handler;
    }

    /**
     * Get route middleware
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get route parameters
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get route name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
