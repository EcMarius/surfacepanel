<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Template\TemplateManager;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;

class TemplateController extends Controller
{
    private TemplateManager $templateManager;
    private TemplateEngine $template;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->templateManager = new TemplateManager($app);
        $this->template = new TemplateEngine($app);
    }

    /**
     * Display admin templates management
     */
    public function adminIndex(Request $request): Response
    {
        $templates = $this->templateManager->all();
        $activeTemplate = config('template.admin_theme', 'default');

        return new Response($this->template->render('admin/templates/index.html.twig', [
            'templates' => $templates,
            'activeTemplate' => $activeTemplate,
            'templateType' => 'admin',
        ]));
    }

    /**
     * Display user template selection
     */
    public function userIndex(Request $request): Response
    {
        // Check if user theme switching is allowed
        if (!config('template.allow_user_themes', true)) {
            $_SESSION['error'] = 'User theme switching is disabled';
            return new RedirectResponse('/user/dashboard');
        }

        $templates = $this->templateManager->all();

        // Get user's selected theme
        $user = Auth::user();
        $userTheme = $user->getPreference('theme', config('template.default_theme', 'default'));

        // Filter to only user-compatible themes
        $templates = array_filter($templates, function($template) {
            return in_array($template['type'] ?? 'user', ['user', 'both']);
        });

        return new Response($this->template->render('user/templates/index.html.twig', [
            'templates' => $templates,
            'activeTemplate' => $userTheme,
            'templateType' => 'user',
        ]));
    }

    /**
     * Upload and install template (admin only)
     */
    public function upload(Request $request): Response
    {
        $file = $request->files->get('template_file');

        if (!$file) {
            $_SESSION['error'] = 'No file uploaded';
            return new RedirectResponse('/admin/templates');
        }

        // Validate file type
        $allowedExtensions = ['zip'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            $_SESSION['error'] = 'Invalid file type. Only ZIP files are allowed.';
            return new RedirectResponse('/admin/templates');
        }

        // Validate file size (max 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB in bytes
        if ($file->getSize() > $maxSize) {
            $_SESSION['error'] = 'File size exceeds maximum allowed size of 50MB';
            return new RedirectResponse('/admin/templates');
        }

        // Move uploaded file to temp location
        $tempPath = sys_get_temp_dir() . '/template_upload_' . uniqid() . '.zip';

        try {
            $file->move(dirname($tempPath), basename($tempPath));
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to upload file: ' . $e->getMessage();
            return new RedirectResponse('/admin/templates');
        }

        // Install template
        try {
            $result = $this->templateManager->install($tempPath);

            if ($result) {
                $_SESSION['success'] = 'Template installed successfully';
            } else {
                $_SESSION['error'] = 'Failed to install template. Please check the template package.';
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Installation failed: ' . $e->getMessage();
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return new RedirectResponse('/admin/templates');
    }

    /**
     * Activate template (admin)
     */
    public function activate(Request $request, string $name): Response
    {
        $type = $request->request->get('type', 'admin');

        try {
            $result = $this->templateManager->activate($name, $type);

            if ($result) {
                // Update config file if activating default theme
                if ($type === 'admin') {
                    $this->updateConfigValue('template.admin_theme', $name);
                } else {
                    $this->updateConfigValue('template.default_theme', $name);
                }

                $_SESSION['success'] = "Template '{$name}' activated successfully";
            } else {
                $_SESSION['error'] = "Failed to activate template '{$name}'";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Activation failed: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/templates');
    }

    /**
     * Set user's preferred template
     */
    public function setUserTemplate(Request $request, string $name): Response
    {
        // Check if user theme switching is allowed
        if (!config('template.allow_user_themes', true)) {
            $_SESSION['error'] = 'User theme switching is disabled';
            return new RedirectResponse('/user/dashboard');
        }

        // Verify template exists and is user-compatible
        $template = $this->templateManager->get($name);

        if (!$template) {
            $_SESSION['error'] = "Template '{$name}' not found";
            return new RedirectResponse('/user/templates');
        }

        if (!in_array($template['type'] ?? 'user', ['user', 'both'])) {
            $_SESSION['error'] = "Template '{$name}' is not available for user selection";
            return new RedirectResponse('/user/templates');
        }

        try {
            $user = Auth::user();
            $user->setPreference('theme', $name);

            $_SESSION['success'] = "Template changed to '{$template['display_name'] ?? $name}'";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to change template: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/templates');
    }

    /**
     * Uninstall template (admin only)
     */
    public function uninstall(Request $request, string $name): Response
    {
        try {
            $result = $this->templateManager->uninstall($name);

            if ($result) {
                $_SESSION['success'] = "Template '{$name}' uninstalled successfully";
            } else {
                $_SESSION['error'] = "Failed to uninstall template '{$name}'. Make sure it's not the active template.";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Uninstall failed: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/templates');
    }

    /**
     * Preview template (AJAX)
     */
    public function preview(Request $request, string $name): JsonResponse
    {
        $template = $this->templateManager->get($name);

        if (!$template) {
            return new JsonResponse(['error' => 'Template not found'], 404);
        }

        // Get preview image
        $previewImage = $template['preview_image'] ?? '/themes/' . $name . '/preview.png';

        return new JsonResponse([
            'name' => $template['name'],
            'display_name' => $template['display_name'] ?? $template['name'],
            'description' => $template['description'] ?? 'No description available',
            'version' => $template['version'],
            'author' => $template['author'] ?? 'Unknown',
            'preview_image' => $previewImage,
            'type' => $template['type'] ?? 'user',
        ]);
    }

    /**
     * Update config value (helper method)
     */
    private function updateConfigValue(string $key, string $value): void
    {
        // In a production system, this would update the .env file or config cache
        // For now, we'll just log it
        logger("Config updated: {$key} = {$value}");

        // Update session for immediate effect
        $_SESSION['config'][$key] = $value;
    }
}
