<?php

namespace VirPanel\Core\Http\Controllers;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;

class FileManagerController extends Controller
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;
    private ?int $accountId = null;
    private ?string $basePath = null;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/database.php';
        $this->db = \Doctrine\DBAL\DriverManager::getConnection($config);
        $app = \VirPanel\Core\Application::getInstance();
        $this->template = new TemplateEngine($app);
        $this->prefix = $config['prefix'] ?? '';
    }

    /**
     * Show file manager interface
     */
    public function index(Request $request, ?int $accountId = null): Response
    {
        if ($accountId) {
            // Admin viewing specific account's files
            $account = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            if (!$account) {
                $_SESSION['error'] = 'Account not found';
                return new RedirectResponse('/admin/accounts');
            }

            $this->accountId = $accountId;
            $this->basePath = '/home/' . $account['username'];
        } else {
            // User viewing their own files
            $user = Auth::user();
            if (!$user) {
                return new RedirectResponse('/login');
            }

            // Get user's account
            $account = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}accounts WHERE username = ?",
                [$user->getUsername()]
            );

            if (!$account) {
                $_SESSION['error'] = 'Account not found';
                return new RedirectResponse('/');
            }

            $this->accountId = $account['id'];
            $this->basePath = '/home/' . $account['username'];
        }

        $path = $request->query->get('path', '');
        $currentPath = $this->basePath . ($path ? '/' . $path : '');

        // Security check - prevent directory traversal
        if (!$this->isPathSafe($currentPath)) {
            $_SESSION['error'] = 'Invalid path';
            return new RedirectResponse($request->headers->get('referer', '/'));
        }

        // Get directory contents
        $items = $this->listDirectory($currentPath);
        $breadcrumbs = $this->getBreadcrumbs($path);

        return new Response($this->template->render('admin/filemanager/index.html.twig', [
            'items' => $items,
            'currentPath' => $path,
            'breadcrumbs' => $breadcrumbs,
            'account' => $account,
            'isAdmin' => $accountId !== null,
        ]));
    }

    /**
     * Upload file
     */
    public function upload(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->request->get('path', '');
        $targetPath = '/home/' . $account['username'] . ($path ? '/' . $path : '');

        if (!$this->isPathSafe($targetPath)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid path'], 400);
        }

        $uploadedFiles = $request->files->get('files', []);
        $uploaded = [];
        $errors = [];

        foreach ($uploadedFiles as $file) {
            $fileName = $file->getClientOriginalName();
            $targetFile = $targetPath . '/' . $fileName;

            try {
                $file->move($targetPath, $fileName);
                $uploaded[] = $fileName;
            } catch (\Exception $e) {
                $errors[] = $fileName . ': ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Some files failed to upload',
                'uploaded' => $uploaded,
                'errors' => $errors
            ], 400);
        }

        return new JsonResponse(['success' => true, 'uploaded' => $uploaded]);
    }

    /**
     * Download file
     */
    public function download(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            $_SESSION['error'] = 'Account not found';
            return new RedirectResponse('/admin/accounts');
        }

        $path = $request->query->get('path', '');
        $filePath = '/home/' . $account['username'] . '/' . $path;

        if (!$this->isPathSafe($filePath) || !file_exists($filePath) || !is_file($filePath)) {
            $_SESSION['error'] = 'File not found';
            return new RedirectResponse($request->headers->get('referer', '/'));
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );

        return $response;
    }

    /**
     * Create new folder
     */
    public function createFolder(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->request->get('path', '');
        $folderName = $request->request->get('name', '');

        if (empty($folderName)) {
            return new JsonResponse(['success' => false, 'error' => 'Folder name is required'], 400);
        }

        $targetPath = '/home/' . $account['username'] . ($path ? '/' . $path : '') . '/' . $folderName;

        if (!$this->isPathSafe($targetPath)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid path'], 400);
        }

        if (file_exists($targetPath)) {
            return new JsonResponse(['success' => false, 'error' => 'Folder already exists'], 400);
        }

        try {
            mkdir($targetPath, 0755, true);
            return new JsonResponse(['success' => true, 'message' => 'Folder created successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete file or folder
     */
    public function delete(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->request->get('path', '');
        $filePath = '/home/' . $account['username'] . '/' . $path;

        if (!$this->isPathSafe($filePath) || !file_exists($filePath)) {
            return new JsonResponse(['success' => false, 'error' => 'File not found'], 404);
        }

        try {
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
            return new JsonResponse(['success' => true, 'message' => 'Deleted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Rename file or folder
     */
    public function rename(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->request->get('path', '');
        $newName = $request->request->get('name', '');

        if (empty($newName)) {
            return new JsonResponse(['success' => false, 'error' => 'New name is required'], 400);
        }

        $oldPath = '/home/' . $account['username'] . '/' . $path;
        $newPath = dirname($oldPath) . '/' . $newName;

        if (!$this->isPathSafe($oldPath) || !$this->isPathSafe($newPath)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid path'], 400);
        }

        if (!file_exists($oldPath)) {
            return new JsonResponse(['success' => false, 'error' => 'File not found'], 404);
        }

        if (file_exists($newPath)) {
            return new JsonResponse(['success' => false, 'error' => 'A file with that name already exists'], 400);
        }

        try {
            rename($oldPath, $newPath);
            return new JsonResponse(['success' => true, 'message' => 'Renamed successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get file content for editing
     */
    public function edit(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->query->get('path', '');
        $filePath = '/home/' . $account['username'] . '/' . $path;

        if (!$this->isPathSafe($filePath) || !file_exists($filePath) || !is_file($filePath)) {
            return new JsonResponse(['success' => false, 'error' => 'File not found'], 404);
        }

        // Check file size (limit to 1MB for editing)
        if (filesize($filePath) > 1024 * 1024) {
            return new JsonResponse(['success' => false, 'error' => 'File too large to edit'], 400);
        }

        try {
            $content = file_get_contents($filePath);
            return new JsonResponse([
                'success' => true,
                'content' => $content,
                'path' => $path,
                'filename' => basename($filePath)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save edited file
     */
    public function save(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->request->get('path', '');
        $content = $request->request->get('content', '');
        $filePath = '/home/' . $account['username'] . '/' . $path;

        if (!$this->isPathSafe($filePath)) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid path'], 400);
        }

        try {
            file_put_contents($filePath, $content);
            return new JsonResponse(['success' => true, 'message' => 'File saved successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Change file permissions
     */
    public function chmod(Request $request, int $accountId): Response
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return new JsonResponse(['success' => false, 'error' => 'Account not found'], 404);
        }

        $path = $request->request->get('path', '');
        $permissions = $request->request->get('permissions', '');
        $filePath = '/home/' . $account['username'] . '/' . $path;

        if (!$this->isPathSafe($filePath) || !file_exists($filePath)) {
            return new JsonResponse(['success' => false, 'error' => 'File not found'], 404);
        }

        // Convert octal string to integer
        $mode = octdec($permissions);

        try {
            chmod($filePath, $mode);
            return new JsonResponse(['success' => true, 'message' => 'Permissions updated']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * List directory contents
     */
    private function listDirectory(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $items = [];
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $path . '/' . $file;
            $relativePath = str_replace($this->basePath . '/', '', $fullPath);

            $items[] = [
                'name' => $file,
                'path' => $relativePath,
                'type' => is_dir($fullPath) ? 'directory' : 'file',
                'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                'modified' => filemtime($fullPath),
                'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4),
                'readable' => is_readable($fullPath),
                'writable' => is_writable($fullPath),
            ];
        }

        // Sort: directories first, then by name
        usort($items, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['type'] === 'directory' ? -1 : 1;
        });

        return $items;
    }

    /**
     * Get breadcrumbs for navigation
     */
    private function getBreadcrumbs(string $path): array
    {
        $parts = array_filter(explode('/', $path));
        $breadcrumbs = [['name' => 'Home', 'path' => '']];

        $currentPath = '';
        foreach ($parts as $part) {
            $currentPath .= ($currentPath ? '/' : '') . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $currentPath];
        }

        return $breadcrumbs;
    }

    /**
     * Check if path is safe (prevent directory traversal)
     */
    private function isPathSafe(string $path): bool
    {
        $realPath = realpath($path) ?: $path;
        $realBase = realpath($this->basePath);

        if (!$realBase) {
            return false;
        }

        return strpos($realPath, $realBase) === 0;
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * Get account by ID
     */
    private function getAccount(int $accountId): ?array
    {
        return $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        ) ?: null;
    }
}
