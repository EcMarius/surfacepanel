<?php

namespace VirPanel\Core;

use VirPanel\Core\Contracts\ModuleInterface;

/**
 * Module Manager
 *
 * Handles loading, installing, and managing modules/plugins
 */
class ModuleManager
{
    /**
     * The application instance
     *
     * @var Application
     */
    protected Application $app;

    /**
     * Loaded modules
     *
     * @var array<string, ModuleInterface>
     */
    protected array $modules = [];

    /**
     * Module manifests cache
     *
     * @var array
     */
    protected array $manifests = [];

    /**
     * Modules base path
     *
     * @var string
     */
    protected string $modulesPath;

    /**
     * Create a new module manager instance
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->modulesPath = $app->basePath('src/modules');
    }

    /**
     * Boot all enabled modules
     *
     * @return array
     */
    public function boot(): array
    {
        $this->discover();

        foreach ($this->manifests as $name => $manifest) {
            if ($manifest['enabled'] ?? true) {
                $this->load($name);
            }
        }

        return $this->modules;
    }

    /**
     * Discover all modules
     *
     * @return void
     */
    protected function discover(): void
    {
        if (!is_dir($this->modulesPath)) {
            return;
        }

        $dirs = scandir($this->modulesPath);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $modulePath = $this->modulesPath . DIRECTORY_SEPARATOR . $dir;

            if (!is_dir($modulePath)) {
                continue;
            }

            $manifestPath = $modulePath . DIRECTORY_SEPARATOR . 'module.json';

            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $this->manifests[$dir] = $manifest;
            }
        }
    }

    /**
     * Load a specific module
     *
     * @param string $name
     * @return ModuleInterface|null
     */
    public function load(string $name): ?ModuleInterface
    {
        if (isset($this->modules[$name])) {
            return $this->modules[$name];
        }

        $manifest = $this->manifests[$name] ?? null;

        if (!$manifest) {
            logger("Module manifest not found for: $name");
            return null;
        }

        // Validate dependencies
        if (!$this->validateDependencies($manifest)) {
            logger("Module dependencies not met for: $name");
            return null;
        }

        // Load module class
        $moduleClass = $manifest['class'] ?? null;

        if (!$moduleClass || !class_exists($moduleClass)) {
            logger("Module class not found for: $name");
            return null;
        }

        try {
            $module = new $moduleClass($this->app);

            if (!$module instanceof ModuleInterface) {
                logger("Module does not implement ModuleInterface: $name");
                return null;
            }

            // Register module
            $module->register();

            // Boot module
            if (method_exists($module, 'boot')) {
                $module->boot();
            }

            $this->modules[$name] = $module;

            logger("Module loaded successfully: $name");

            return $module;
        } catch (\Throwable $e) {
            logger("Failed to load module $name: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate module dependencies
     *
     * @param array $manifest
     * @return bool
     */
    protected function validateDependencies(array $manifest): bool
    {
        $dependencies = $manifest['dependencies'] ?? [];

        foreach ($dependencies as $dependency => $version) {
            // Check if dependency is loaded
            if (!isset($this->modules[$dependency])) {
                return false;
            }

            // Version check (simplified)
            // In a real implementation, use version_compare
        }

        return true;
    }

    /**
     * Install a module from uploaded package
     *
     * @param string $packagePath
     * @return bool
     */
    public function install(string $packagePath): bool
    {
        // Validate package
        if (!file_exists($packagePath)) {
            return false;
        }

        $zip = new \ZipArchive();

        if ($zip->open($packagePath) !== true) {
            return false;
        }

        // Extract to temporary directory
        $tempDir = sys_get_temp_dir() . '/sp_module_' . uniqid();
        $zip->extractTo($tempDir);
        $zip->close();

        // Validate module structure
        $manifestPath = $tempDir . '/module.json';

        if (!file_exists($manifestPath)) {
            $this->cleanup($tempDir);
            return false;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (!$this->validateManifest($manifest)) {
            $this->cleanup($tempDir);
            return false;
        }

        // Verify signature (if present)
        if (isset($manifest['signature'])) {
            if (!$this->verifySignature($tempDir, $manifest['signature'])) {
                $this->cleanup($tempDir);
                return false;
            }
        }

        // Move to modules directory
        $moduleName = $manifest['name'];
        $destination = $this->modulesPath . DIRECTORY_SEPARATOR . $moduleName;

        if (file_exists($destination)) {
            // Module already exists, backup first
            $backup = $destination . '.backup.' . time();
            rename($destination, $backup);
        }

        rename($tempDir, $destination);

        // Run installation scripts if any
        if (isset($manifest['install_script'])) {
            $scriptPath = $destination . DIRECTORY_SEPARATOR . $manifest['install_script'];
            if (file_exists($scriptPath)) {
                include $scriptPath;
            }
        }

        // Update database
        $this->registerModuleInDatabase($manifest);

        logger("Module installed successfully: $moduleName");

        return true;
    }

    /**
     * Validate module manifest
     *
     * @param array|null $manifest
     * @return bool
     */
    protected function validateManifest(?array $manifest): bool
    {
        if (!$manifest) {
            return false;
        }

        $required = ['name', 'version', 'class'];

        foreach ($required as $field) {
            if (!isset($manifest[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify module signature
     *
     * @param string $modulePath
     * @param string $signature
     * @return bool
     */
    protected function verifySignature(string $modulePath, string $signature): bool
    {
        // Implement GPG signature verification
        // For now, return true
        return true;
    }

    /**
     * Register module in database
     *
     * @param array $manifest
     * @return void
     */
    protected function registerModuleInDatabase(array $manifest): void
    {
        // Store module metadata in database
        // Implementation depends on database schema
    }

    /**
     * Uninstall a module
     *
     * @param string $name
     * @return bool
     */
    public function uninstall(string $name): bool
    {
        $modulePath = $this->modulesPath . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($modulePath)) {
            return false;
        }

        $manifest = $this->manifests[$name] ?? null;

        if (!$manifest) {
            return false;
        }

        // Run uninstall script if any
        if (isset($manifest['uninstall_script'])) {
            $scriptPath = $modulePath . DIRECTORY_SEPARATOR . $manifest['uninstall_script'];
            if (file_exists($scriptPath)) {
                include $scriptPath;
            }
        }

        // Remove from database
        $this->unregisterModuleFromDatabase($name);

        // Remove directory
        $this->removeDirectory($modulePath);

        // Remove from loaded modules
        unset($this->modules[$name], $this->manifests[$name]);

        logger("Module uninstalled successfully: $name");

        return true;
    }

    /**
     * Unregister module from database
     *
     * @param string $name
     * @return void
     */
    protected function unregisterModuleFromDatabase(string $name): void
    {
        // Remove module metadata from database
    }

    /**
     * Enable a module
     *
     * @param string $name
     * @return bool
     */
    public function enable(string $name): bool
    {
        // Update module status in database
        logger("Module enabled: $name");
        return true;
    }

    /**
     * Disable a module
     *
     * @param string $name
     * @return bool
     */
    public function disable(string $name): bool
    {
        // Update module status in database
        // Unload if currently loaded
        if (isset($this->modules[$name])) {
            unset($this->modules[$name]);
        }

        logger("Module disabled: $name");
        return true;
    }

    /**
     * Get all modules
     *
     * @return array
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Get a specific module
     *
     * @param string $name
     * @return ModuleInterface|null
     */
    public function get(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Check if a module exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
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
