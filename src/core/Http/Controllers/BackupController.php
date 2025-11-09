<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class BackupController extends Controller
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->template = new TemplateEngine($app);
        $this->prefix = config('database.prefix', 'vp_');
    }

    /**
     * Display backups overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get account details
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        // Get all backups for this account
        $backups = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}backups WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        // Calculate total backup size
        $totalSize = 0;
        foreach ($backups as $backup) {
            $totalSize += $backup['size'] ?? 0;
        }

        return new Response($this->template->render('user/backup/index.html.twig', [
            'backups' => $backups,
            'account' => $account,
            'total_size' => $totalSize,
        ]));
    }

    /**
     * Create new backup
     */
    public function create(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $type = $request->request->get('type', 'full'); // full, files, databases, email

        // Get account details
        $account = $this->db->fetchAssociative(
            "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        if (!$account) {
            $_SESSION['error'] = 'Account not found';
            return new RedirectResponse('/user/backup');
        }

        try {
            $this->db->beginTransaction();

            // Generate backup filename
            $timestamp = date('Y-m-d_His');
            $filename = "{$account['username']}_{$type}_{$timestamp}.tar.gz";
            $backupPath = "/var/virpanel/backups/{$account['username']}";

            // Create backup directory if it doesn't exist
            if (!is_dir($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $fullPath = "{$backupPath}/{$filename}";

            // Create backup record
            $this->db->insert($this->prefix . 'backups', [
                'account_id' => $accountId,
                'filename' => $filename,
                'path' => $fullPath,
                'type' => $type,
                'status' => 'pending',
                'size' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $backupId = $this->db->lastInsertId();

            $this->db->commit();

            // Queue backup job (in production, this would be handled by a background worker)
            $this->executeBackup($backupId, $account['username'], $type, $fullPath);

            logger("Backup created: {$filename} for {$account['username']}");

            $_SESSION['success'] = 'Backup created successfully. This may take a few minutes to complete.';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create backup: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/backup');
    }

    /**
     * Download backup
     */
    public function download(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        // Verify backup belongs to user
        $backup = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}backups WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );

        if (!$backup) {
            $_SESSION['error'] = 'Backup not found';
            return new RedirectResponse('/user/backup');
        }

        if ($backup['status'] !== 'completed') {
            $_SESSION['error'] = 'Backup is not ready for download';
            return new RedirectResponse('/user/backup');
        }

        $filePath = $backup['path'];

        if (!file_exists($filePath)) {
            $_SESSION['error'] = 'Backup file not found';
            return new RedirectResponse('/user/backup');
        }

        // Update download count
        $this->db->update($this->prefix . 'backups', [
            'download_count' => ($backup['download_count'] ?? 0) + 1,
            'last_downloaded_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        logger("Backup downloaded: {$backup['filename']}");

        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/x-gzip',
            'Content-Disposition' => 'attachment; filename="' . $backup['filename'] . '"',
        ]);
    }

    /**
     * Delete backup
     */
    public function delete(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->beginTransaction();

            // Verify backup belongs to user
            $backup = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}backups WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$backup) {
                $_SESSION['error'] = 'Backup not found';
                return new RedirectResponse('/user/backup');
            }

            // Delete backup file
            if (file_exists($backup['path'])) {
                @unlink($backup['path']);
            }

            // Delete backup record
            $this->db->delete($this->prefix . 'backups', ['id' => $id]);

            $this->db->commit();

            logger("Backup deleted: {$backup['filename']}");

            $_SESSION['success'] = 'Backup deleted successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete backup: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/backup');
    }

    /**
     * Restore from backup (Admin only)
     */
    public function restore(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        // Verify backup belongs to user
        $backup = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}backups WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );

        if (!$backup) {
            $_SESSION['error'] = 'Backup not found';
            return new RedirectResponse('/user/backup');
        }

        if ($backup['status'] !== 'completed') {
            $_SESSION['error'] = 'Backup is not ready for restoration';
            return new RedirectResponse('/user/backup');
        }

        try {
            $account = $this->db->fetchAssociative(
                "SELECT username FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            // Execute restore (in production, this would be handled by a background worker)
            $this->executeRestore($backup['path'], $account['username'], $backup['type']);

            logger("Backup restored: {$backup['filename']} for {$account['username']}");

            $_SESSION['success'] = 'Backup restoration started. This may take several minutes.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to restore backup: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/backup');
    }

    /**
     * Get user's account ID
     */
    private function getUserAccountId(): ?int
    {
        $user = Auth::user();

        $account = $this->db->fetchAssociative(
            "SELECT id FROM {$this->prefix}accounts WHERE user_id = ? LIMIT 1",
            [$user->getId()]
        );

        return $account ? (int)$account['id'] : null;
    }

    /**
     * Execute backup process
     */
    private function executeBackup(int $backupId, string $username, string $type, string $outputPath): void
    {
        try {
            $homeDir = "/home/{$username}";
            $tempDir = "/tmp/backup_{$username}_" . time();

            mkdir($tempDir, 0700, true);

            $files = [];

            switch ($type) {
                case 'full':
                    // Full account backup
                    $files = [
                        $homeDir . '/public_html',
                        $homeDir . '/mail',
                        $homeDir . '/.my.cnf',
                    ];
                    // Also backup databases
                    $this->backupDatabases($tempDir, $username);
                    break;

                case 'files':
                    // Files only
                    $files = [$homeDir . '/public_html'];
                    break;

                case 'databases':
                    // Databases only
                    $this->backupDatabases($tempDir, $username);
                    break;

                case 'email':
                    // Email only
                    $files = [$homeDir . '/mail'];
                    break;
            }

            // Create tarball
            if (!empty($files)) {
                $fileList = implode(' ', array_map('escapeshellarg', $files));
                exec("tar -czf " . escapeshellarg($outputPath) . " {$fileList} 2>&1", $output, $returnCode);
            } else {
                // Create from temp directory
                exec("tar -czf " . escapeshellarg($outputPath) . " -C " . escapeshellarg($tempDir) . " . 2>&1", $output, $returnCode);
            }

            // Clean up temp directory
            exec("rm -rf " . escapeshellarg($tempDir));

            if ($returnCode === 0 && file_exists($outputPath)) {
                $size = filesize($outputPath);

                // Update backup status
                $this->db->update($this->prefix . 'backups', [
                    'status' => 'completed',
                    'size' => $size,
                    'completed_at' => date('Y-m-d H:i:s'),
                ], ['id' => $backupId]);

                logger("Backup completed successfully: {$outputPath}");
            } else {
                throw new \Exception('Backup creation failed: ' . implode("\n", $output));
            }
        } catch (\Exception $e) {
            // Update backup status to failed
            $this->db->update($this->prefix . 'backups', [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ], ['id' => $backupId]);

            logger("Backup failed: " . $e->getMessage());
        }
    }

    /**
     * Backup databases for account
     */
    private function backupDatabases(string $outputDir, string $username): void
    {
        $accountId = $this->db->fetchOne(
            "SELECT id FROM {$this->prefix}accounts WHERE username = ?",
            [$username]
        );

        if (!$accountId) {
            return;
        }

        // Get all databases for this account
        $databases = $this->db->fetchAllAssociative(
            "SELECT name FROM {$this->prefix}databases WHERE account_id = ?",
            [$accountId]
        );

        foreach ($databases as $database) {
            $dbName = $database['name'];
            $dumpFile = "{$outputDir}/{$dbName}.sql";

            // MySQL dump
            exec("mysqldump " . escapeshellarg($dbName) . " > " . escapeshellarg($dumpFile) . " 2>&1");
        }
    }

    /**
     * Execute restore process
     */
    private function executeRestore(string $backupPath, string $username, string $type): void
    {
        $homeDir = "/home/{$username}";
        $tempDir = "/tmp/restore_{$username}_" . time();

        mkdir($tempDir, 0700, true);

        // Extract backup
        exec("tar -xzf " . escapeshellarg($backupPath) . " -C " . escapeshellarg($tempDir) . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract backup');
        }

        // Restore based on type
        switch ($type) {
            case 'full':
            case 'files':
                // Restore files
                if (is_dir($tempDir . '/public_html')) {
                    exec("cp -R " . escapeshellarg($tempDir . '/public_html') . "/* " . escapeshellarg($homeDir . '/public_html/') . " 2>&1");
                }
                break;

            case 'databases':
                // Restore databases
                $sqlFiles = glob($tempDir . '/*.sql');
                foreach ($sqlFiles as $sqlFile) {
                    $dbName = basename($sqlFile, '.sql');
                    exec("mysql " . escapeshellarg($dbName) . " < " . escapeshellarg($sqlFile) . " 2>&1");
                }
                break;

            case 'email':
                // Restore email
                if (is_dir($tempDir . '/mail')) {
                    exec("cp -R " . escapeshellarg($tempDir . '/mail') . "/* " . escapeshellarg($homeDir . '/mail/') . " 2>&1");
                }
                break;
        }

        // Clean up temp directory
        exec("rm -rf " . escapeshellarg($tempDir));

        logger("Restore completed for {$username}");
    }
}
