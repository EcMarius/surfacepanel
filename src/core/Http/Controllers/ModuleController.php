<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\ModuleManager;
use VirPanel\Core\Application;

class ModuleController extends Controller
{
    private ModuleManager $moduleManager;
    private TemplateEngine $template;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->moduleManager = new ModuleManager($app);
        $this->template = new TemplateEngine($app);
    }

    /**
     * Display list of modules
     */
    public function index(Request $request): Response
    {
        // Discover all modules
        $this->moduleManager->boot();

        // Get all modules with their manifests
        $modulesPath = Application::getInstance()->basePath('src/modules');
        $modules = [];

        if (is_dir($modulesPath)) {
            $dirs = scandir($modulesPath);

            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }

                $modulePath = $modulesPath . DIRECTORY_SEPARATOR . $dir;

                if (!is_dir($modulePath)) {
                    continue;
                }

                $manifestPath = $modulePath . DIRECTORY_SEPARATOR . 'module.json';

                if (file_exists($manifestPath)) {
                    $manifest = json_decode(file_get_contents($manifestPath), true);
                    $manifest['directory'] = $dir;
                    $manifest['enabled'] = $this->moduleManager->has($dir);
                    $modules[] = $manifest;
                }
            }
        }

        return new Response($this->template->render('admin/modules/index.html.twig', [
            'modules' => $modules,
        ]));
    }

    /**
     * Upload and install module
     */
    public function upload(Request $request): Response
    {
        $file = $request->files->get('module_file');

        if (!$file) {
            $_SESSION['error'] = 'No file uploaded';
            return new RedirectResponse('/admin/modules');
        }

        // Validate file type
        $allowedExtensions = ['zip'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            $_SESSION['error'] = 'Invalid file type. Only ZIP files are allowed.';
            return new RedirectResponse('/admin/modules');
        }

        // Validate file size (max 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB in bytes
        if ($file->getSize() > $maxSize) {
            $_SESSION['error'] = 'File size exceeds maximum allowed size of 50MB';
            return new RedirectResponse('/admin/modules');
        }

        // Move uploaded file to temp location
        $tempPath = sys_get_temp_dir() . '/module_upload_' . uniqid() . '.zip';

        try {
            $file->move(dirname($tempPath), basename($tempPath));
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to upload file: ' . $e->getMessage();
            return new RedirectResponse('/admin/modules');
        }

        // Install module
        try {
            $result = $this->moduleManager->install($tempPath);

            if ($result) {
                $_SESSION['success'] = 'Module installed successfully';
            } else {
                $_SESSION['error'] = 'Failed to install module. Please check the module package.';
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Installation failed: ' . $e->getMessage();
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return new RedirectResponse('/admin/modules');
    }

    /**
     * Enable module
     */
    public function enable(Request $request, string $name): Response
    {
        try {
            $result = $this->moduleManager->enable($name);

            if ($result) {
                // Also load the module
                $this->moduleManager->load($name);
                $_SESSION['success'] = "Module '{$name}' enabled successfully";
            } else {
                $_SESSION['error'] = "Failed to enable module '{$name}'";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Enable failed: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/modules');
    }

    /**
     * Disable module
     */
    public function disable(Request $request, string $name): Response
    {
        try {
            $result = $this->moduleManager->disable($name);

            if ($result) {
                $_SESSION['success'] = "Module '{$name}' disabled successfully";
            } else {
                $_SESSION['error'] = "Failed to disable module '{$name}'";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Disable failed: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/modules');
    }

    /**
     * Uninstall module
     */
    public function uninstall(Request $request, string $name): Response
    {
        try {
            $result = $this->moduleManager->uninstall($name);

            if ($result) {
                $_SESSION['success'] = "Module '{$name}' uninstalled successfully";
            } else {
                $_SESSION['error'] = "Failed to uninstall module '{$name}'";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Uninstall failed: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/modules');
    }

    /**
     * Get module details (AJAX)
     */
    public function details(Request $request, string $name): JsonResponse
    {
        $modulesPath = Application::getInstance()->basePath('src/modules');
        $manifestPath = $modulesPath . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'module.json';

        if (!file_exists($manifestPath)) {
            return new JsonResponse(['error' => 'Module not found'], 404);
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['enabled'] = $this->moduleManager->has($name);

        return new JsonResponse($manifest);
    }
}
