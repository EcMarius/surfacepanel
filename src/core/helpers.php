<?php

use VirPanel\Core\Application;

if (!function_exists('app')) {
    /**
     * Get the available application instance.
     *
     * @param string|null $abstract
     * @param array $parameters
     * @return mixed|Application
     */
    function app(?string $abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Application::getInstance();
        }

        return Application::getInstance()->getContainer()->get($abstract);
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return value($default);
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return app('config');
        }

        if (is_array($key)) {
            return app('config')->set($key);
        }

        return app('config')->get($key, $default);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    function value(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param string $path
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param string $path
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param string $path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('response')) {
    /**
     * Create a new response instance or helper.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        return new \Symfony\Component\HttpFoundation\Response($content, $status, $headers);
    }
}

if (!function_exists('json_response')) {
    /**
     * Create a new JSON response instance.
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    function json_response(mixed $data = [], int $status = 200, array $headers = [], int $options = 0): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse($data, $status, $headers, $options);
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message to the logs.
     *
     * @param string|null $message
     * @param array $context
     * @return \Psr\Log\LoggerInterface|null
     */
    function logger(?string $message = null, array $context = []): ?\Psr\Log\LoggerInterface
    {
        $log = app('logger');

        if (is_null($message)) {
            return $log;
        }

        return $log->info($message, $context);
    }
}

if (!function_exists('cache')) {
    /**
     * Get / set the specified cache value.
     *
     * @param string|null $key
     * @param mixed $value
     * @param int|null $ttl
     * @return mixed
     */
    function cache(?string $key = null, mixed $value = null, ?int $ttl = null): mixed
    {
        $cache = app('cache');

        if (is_null($key)) {
            return $cache;
        }

        if (is_null($value)) {
            return $cache->get($key);
        }

        return $cache->set($key, $value, $ttl);
    }
}

if (!function_exists('encrypt')) {
    /**
     * Encrypt the given value.
     *
     * @param mixed $value
     * @return string
     */
    function encrypt(mixed $value): string
    {
        return app('encrypter')->encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    /**
     * Decrypt the given value.
     *
     * @param string $value
     * @return mixed
     */
    function decrypt(string $value): mixed
    {
        return app('encrypter')->decrypt($value);
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event and call the listeners.
     *
     * @param object|string $event
     * @param mixed $payload
     * @return void
     */
    function event(object|string $event, mixed $payload = []): void
    {
        app()->getEventDispatcher()->dispatch($event, $payload);
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HttpException with the given data.
     *
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return never
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        throw new \Symfony\Component\HttpKernel\Exception\HttpException($code, $message, null, $headers);
    }
}

if (!function_exists('url')) {
    /**
     * Generate a url for the application.
     *
     * @param string $path
     * @param array $parameters
     * @param bool|null $secure
     * @return string
     */
    function url(string $path = '', array $parameters = [], ?bool $secure = null): string
    {
        $url = app('url');
        return $url->to($path, $parameters, $secure);
    }
}

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     *
     * @param string $name
     * @param array $parameters
     * @return string
     */
    function route(string $name, array $parameters = []): string
    {
        return app('url')->route($name, $parameters);
    }
}

if (!function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param \DateTimeZone|string|null $tz
     * @return \DateTime
     */
    function now(\DateTimeZone|string|null $tz = null): \DateTime
    {
        return new \DateTime('now', $tz ? new \DateTimeZone($tz) : null);
    }
}

if (!function_exists('collect')) {
    /**
     * Create a collection from the given value.
     *
     * @param mixed $value
     * @return array
     */
    function collect(mixed $value = []): array
    {
        return is_array($value) ? $value : [$value];
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param mixed ...$args
     * @return never
     */
    function dd(...$args): never
    {
        foreach ($args as $arg) {
            var_dump($arg);
        }

        die(1);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump the passed variables.
     *
     * @param mixed ...$args
     * @return void
     */
    function dump(...$args): void
    {
        foreach ($args as $arg) {
            var_dump($arg);
        }
    }
}
