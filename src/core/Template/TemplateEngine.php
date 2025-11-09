<?php

namespace VirPanel\Core\Template;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use VirPanel\Core\Application;

/**
 * Template Engine
 *
 * Manages Twig template rendering with theme support
 */
class TemplateEngine
{
    /**
     * Application instance
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Twig environment
     *
     * @var Environment
     */
    protected Environment $twig;

    /**
     * Filesystem loader
     *
     * @var FilesystemLoader
     */
    protected FilesystemLoader $loader;

    /**
     * Current theme
     *
     * @var string
     */
    protected string $theme;

    /**
     * Template paths
     *
     * @var array
     */
    protected array $paths = [];

    /**
     * Create a new template engine instance
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->theme = config('template.default_theme', 'default');

        $this->setupLoader();
        $this->setupEnvironment();
    }

    /**
     * Setup the filesystem loader
     *
     * @return void
     */
    protected function setupLoader(): void
    {
        $this->loader = new FilesystemLoader();

        // Add default template paths
        $this->addPath(base_path('public/themes/' . $this->theme), 'theme');
        $this->addPath(base_path('resources/views'), 'views');
        $this->addPath(base_path('resources/views/admin'), 'admin');
        $this->addPath(base_path('resources/views/user'), 'user');
        $this->addPath(base_path('resources/views/layouts'), 'layouts');
        $this->addPath(base_path('resources/views/components'), 'components');
    }

    /**
     * Setup the Twig environment
     *
     * @return void
     */
    protected function setupEnvironment(): void
    {
        $this->twig = new Environment($this->loader, [
            'cache' => config('template.cache_enabled', true)
                ? storage_path('cache/views')
                : false,
            'debug' => config('app.debug', false),
            'auto_reload' => config('app.debug', false),
            'autoescape' => 'html',
            'strict_variables' => config('app.debug', false),
        ]);

        // Add debug extension if in debug mode
        if (config('app.debug')) {
            $this->twig->addExtension(new DebugExtension());
        }

        // Add custom extensions
        $this->addExtensions();

        // Add global variables
        $this->addGlobals();
    }

    /**
     * Add custom Twig extensions
     *
     * @return void
     */
    protected function addExtensions(): void
    {
        // Add custom filters
        $this->twig->addFilter(new \Twig\TwigFilter('format_bytes', function ($bytes) {
            return $this->formatBytes($bytes);
        }));

        $this->twig->addFilter(new \Twig\TwigFilter('format_date', function ($date, $format = 'Y-m-d H:i:s') {
            if ($date instanceof \DateTime) {
                return $date->format($format);
            }
            return date($format, strtotime($date));
        }));

        $this->twig->addFilter(new \Twig\TwigFilter('time_ago', function ($date) {
            return $this->timeAgo($date);
        }));

        // Add custom functions
        $this->twig->addFunction(new \Twig\TwigFunction('route', function ($name, $params = []) {
            return route($name, $params);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('url', function ($path = '', $params = []) {
            return url($path, $params);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($path) {
            return url('/assets/' . $path);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('config', function ($key, $default = null) {
            return config($key, $default);
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('csrf_token', function () {
            return $_SESSION['csrf_token'] ?? '';
        }));

        $this->twig->addFunction(new \Twig\TwigFunction('csrf_field', function () {
            $token = $_SESSION['csrf_token'] ?? '';
            return '<input type="hidden" name="_token" value="' . $token . '">';
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new \Twig\TwigFunction('method_field', function ($method) {
            return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
        }, ['is_safe' => ['html']]));
    }

    /**
     * Add global variables
     *
     * @return void
     */
    protected function addGlobals(): void
    {
        $this->twig->addGlobal('app', [
            'name' => config('app.name', 'VirPanel'),
            'version' => '0.1.0',
            'url' => config('app.url', ''),
            'debug' => config('app.debug', false),
            'session' => new class {
                public function get($key, $default = null) {
                    $value = $_SESSION[$key] ?? $default;
                    // Clear flash messages after retrieval
                    if (in_array($key, ['success', 'error', 'errors', 'old'])) {
                        unset($_SESSION[$key]);
                    }
                    return $value;
                }

                public function has($key) {
                    return isset($_SESSION[$key]);
                }
            },
        ]);

        $this->twig->addGlobal('theme', $this->theme);

        // Add user if authenticated
        if (isset($_SESSION['user'])) {
            $this->twig->addGlobal('user', $_SESSION['user']);
            $this->twig->addGlobal('authenticated', true);
        } else {
            $this->twig->addGlobal('authenticated', false);
        }
    }

    /**
     * Render a template
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }

    /**
     * Render a template from a specific namespace
     *
     * @param string $namespace
     * @param string $template
     * @param array $data
     * @return string
     */
    public function renderFrom(string $namespace, string $template, array $data = []): string
    {
        return $this->twig->render("@{$namespace}/{$template}", $data);
    }

    /**
     * Check if a template exists
     *
     * @param string $template
     * @return bool
     */
    public function exists(string $template): bool
    {
        return $this->loader->exists($template);
    }

    /**
     * Add a template path
     *
     * @param string $path
     * @param string|null $namespace
     * @return void
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        if (!is_dir($path)) {
            return;
        }

        if ($namespace) {
            $this->loader->addPath($path, $namespace);
        } else {
            $this->loader->addPath($path);
        }

        $this->paths[] = ['path' => $path, 'namespace' => $namespace];
    }

    /**
     * Set the current theme
     *
     * @param string $theme
     * @return void
     */
    public function setTheme(string $theme): void
    {
        $this->theme = $theme;

        // Update theme path
        $themePath = base_path('public/themes/' . $theme);

        if (is_dir($themePath)) {
            $this->loader->setPaths([$themePath], 'theme');
        }

        $this->twig->addGlobal('theme', $theme);
    }

    /**
     * Get the current theme
     *
     * @return string
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * Get the Twig environment
     *
     * @return Environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    /**
     * Add a global variable
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function addGlobal(string $name, mixed $value): void
    {
        $this->twig->addGlobal($name, $value);
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Convert date to time ago format
     *
     * @param string|\DateTime $date
     * @return string
     */
    protected function timeAgo(string|\DateTime $date): string
    {
        if ($date instanceof \DateTime) {
            $timestamp = $date->getTimestamp();
        } else {
            $timestamp = strtotime($date);
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        } else {
            $years = floor($diff / 31536000);
            return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
        }
    }
}
