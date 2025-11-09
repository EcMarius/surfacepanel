<?php

namespace VirPanel\Core\Template;

use VirPanel\Core\Application;

/**
 * Template Manager
 *
 * Manages template/theme installation, activation, and configuration
 */
class TemplateManager
{
    /**
     * Application instance
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Templates path
     *
     * @var string
     */
    protected string $templatesPath;

    /**
     * Installed templates cache
     *
     * @var array
     */
    protected array $templates = [];

    /**
     * Create a new template manager instance
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->templatesPath = base_path('public/themes');
    }

    /**
     * Discover all installed templates
     *
     * @return array
     */
    public function discover(): array
    {
        if (!is_dir($this->templatesPath)) {
            mkdir($this->templatesPath, 0755, true);
            return [];
        }

        $this->templates = [];
        $dirs = scandir($this->templatesPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $templatePath = $this->templatesPath . DIRECTORY_SEPARATOR . $dir;

            if (!is_dir($templatePath)) {
                continue;
            }

            $configPath = $templatePath . DIRECTORY_SEPARATOR . 'theme.json';

            if (file_exists($configPath)) {
                $config = json_decode(file_get_contents($configPath), true);
                $this->templates[$dir] = $config;
            }
        }

        return $this->templates;
    }

    /**
     * Get all templates
     *
     * @return array
     */
    public function all(): array
    {
        if (empty($this->templates)) {
            $this->discover();
        }

        return $this->templates;
    }

    /**
     * Get a specific template
     *
     * @param string $name
     * @return array|null
     */
    public function get(string $name): ?array
    {
        if (empty($this->templates)) {
            $this->discover();
        }

        return $this->templates[$name] ?? null;
    }

    /**
     * Install a template from a package
     *
     * @param string $packagePath
     * @return bool
     */
    public function install(string $packagePath): bool
    {
        if (!file_exists($packagePath)) {
            logger("Template package not found: {$packagePath}");
            return false;
        }

        $zip = new \ZipArchive();

        if ($zip->open($packagePath) !== true) {
            logger("Failed to open template package: {$packagePath}");
            return false;
        }

        // Extract to temporary directory
        $tempDir = sys_get_temp_dir() . '/vp_template_' . uniqid();
        $zip->extractTo($tempDir);
        $zip->close();

        // Validate template structure
        $configPath = $tempDir . '/theme.json';

        if (!file_exists($configPath)) {
            $this->cleanup($tempDir);
            logger('Invalid template package: theme.json not found');
            return false;
        }

        $config = json_decode(file_get_contents($configPath), true);

        if (!$this->validateConfig($config)) {
            $this->cleanup($tempDir);
            logger('Invalid template configuration');
            return false;
        }

        $templateName = $config['name'];
        $destination = $this->templatesPath . DIRECTORY_SEPARATOR . $templateName;

        // Check if template already exists
        if (is_dir($destination)) {
            $backup = $destination . '.backup.' . time();
            rename($destination, $backup);
            logger("Existing template backed up to: {$backup}");
        }

        // Move to templates directory
        rename($tempDir, $destination);

        // Update database
        $this->registerTemplate($config);

        logger("Template installed successfully: {$templateName}");

        return true;
    }

    /**
     * Uninstall a template
     *
     * @param string $name
     * @return bool
     */
    public function uninstall(string $name): bool
    {
        $templatePath = $this->templatesPath . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($templatePath)) {
            return false;
        }

        // Cannot uninstall if it's the active template
        $activeTemplate = config('template.default_theme', 'default');

        if ($name === $activeTemplate) {
            logger("Cannot uninstall active template: {$name}");
            return false;
        }

        // Remove directory
        $this->removeDirectory($templatePath);

        // Update database
        $this->unregisterTemplate($name);

        logger("Template uninstalled successfully: {$name}");

        return true;
    }

    /**
     * Activate a template
     *
     * @param string $name
     * @param string $type Optional: 'admin', 'user', or 'both'
     * @return bool
     */
    public function activate(string $name, string $type = 'user'): bool
    {
        $template = $this->get($name);

        if (!$template) {
            logger("Template not found: {$name}");
            return false;
        }

        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        try {
            // Deactivate other templates of the same type
            if ($type === 'both' || $type === 'user') {
                $db->executeStatement(
                    "UPDATE {$prefix}templates SET is_active = 0 WHERE type IN ('user', 'both')"
                );
            }

            if ($type === 'both' || $type === 'admin') {
                $db->executeStatement(
                    "UPDATE {$prefix}templates SET is_active = 0 WHERE type IN ('admin', 'both')"
                );
            }

            // Activate this template
            $db->update($prefix . 'templates', [
                'is_active' => true,
            ], ['name' => $name]);

            logger("Template activated: {$name} (type: {$type})");

            return true;
        } catch (\Throwable $e) {
            logger("Failed to activate template {$name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the active template for a type
     *
     * @param string $type 'admin' or 'user'
     * @return array|null
     */
    public function getActive(string $type = 'user'): ?array
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $template = $db->fetchAssociative(
            "SELECT * FROM {$prefix}templates
             WHERE is_active = 1
             AND (type = ? OR type = 'both')
             LIMIT 1",
            [$type]
        );

        return $template ?: null;
    }

    /**
     * Validate template configuration
     *
     * @param array|null $config
     * @return bool
     */
    protected function validateConfig(?array $config): bool
    {
        if (!$config) {
            return false;
        }

        $required = ['name', 'version', 'type'];

        foreach ($required as $field) {
            if (!isset($config[$field])) {
                return false;
            }
        }

        // Validate type
        if (!in_array($config['type'], ['admin', 'user', 'both'])) {
            return false;
        }

        return true;
    }

    /**
     * Register template in database
     *
     * @param array $config
     * @return void
     */
    protected function registerTemplate(array $config): void
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $data = [
            'name' => $config['name'],
            'display_name' => $config['display_name'] ?? $config['name'],
            'description' => $config['description'] ?? null,
            'version' => $config['version'],
            'author' => $config['author'] ?? null,
            'type' => $config['type'],
            'path' => $this->templatesPath . DIRECTORY_SEPARATOR . $config['name'],
            'preview_image' => $config['preview_image'] ?? null,
            'config' => isset($config['config']) ? json_encode($config['config']) : null,
            'is_active' => false,
            'is_default' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Check if already exists
        $exists = $db->fetchOne(
            "SELECT id FROM {$prefix}templates WHERE name = ?",
            [$config['name']]
        );

        if ($exists) {
            $db->update($prefix . 'templates', $data, ['name' => $config['name']]);
        } else {
            $db->insert($prefix . 'templates', $data);
        }
    }

    /**
     * Unregister template from database
     *
     * @param string $name
     * @return void
     */
    protected function unregisterTemplate(string $name): void
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        $db->delete($prefix . 'templates', ['name' => $name]);
    }

    /**
     * Cleanup directory
     *
     * @param string $dir
     * @return void
     */
    protected function cleanup(string $dir): void
    {
        if (is_dir($dir)) {
            $this->removeDirectory($dir);
        }
    }

    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return void
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
