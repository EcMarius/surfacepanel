<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Backup;
use App\Models\BackupEncryption;
use Illuminate\Support\Facades\DB;

class RestoreService
{
    protected $restorePath = '/tmp/vp_restore';

    /**
     * Preview backup contents without extracting
     */
    public function previewBackup(Backup $backup): array
    {
        $backupPath = $backup->path;

        // Decrypt if encrypted
        if ($backup->is_encrypted) {
            $backupPath = $this->decryptBackup($backup);
        }

        try {
            // List contents of tarball
            $command = sprintf('tar -tzf %s', escapeshellarg($backupPath));
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to list backup contents');
            }

            $preview = [
                'files' => [],
                'databases' => [],
                'emails' => [],
                'config' => null,
            ];

            foreach ($output as $line) {
                if (strpos($line, 'files/') === 0) {
                    $preview['files'][] = str_replace('files/', '', $line);
                } elseif (strpos($line, 'databases/') === 0) {
                    $file = str_replace('databases/', '', $line);
                    if (substr($file, -7) === '.sql.gz' || substr($file, -4) === '.sql') {
                        $preview['databases'][] = $file;
                    }
                } elseif (strpos($line, 'emails/') === 0) {
                    $preview['emails'][] = str_replace('emails/', '', $line);
                } elseif ($line === 'account_config.json') {
                    $preview['config'] = true;
                }
            }

            return [
                'success' => true,
                'backup' => $backup,
                'preview' => $preview,
                'file_count' => count($preview['files']),
                'database_count' => count($preview['databases']),
                'email_count' => count($preview['emails']),
            ];
        } finally {
            // Clean up decrypted file if created
            if ($backup->is_encrypted && $backupPath !== $backup->path && file_exists($backupPath)) {
                unlink($backupPath);
            }
        }
    }

    /**
     * Restore full backup
     */
    public function restoreFullBackup(Backup $backup, Account $account, array $options = []): bool
    {
        $restoreFiles = $options['restore_files'] ?? true;
        $restoreDatabases = $options['restore_databases'] ?? true;
        $restoreEmails = $options['restore_emails'] ?? true;
        $restoreConfig = $options['restore_config'] ?? true;

        $backupPath = $backup->path;

        // Decrypt if encrypted
        if ($backup->is_encrypted) {
            $backupPath = $this->decryptBackup($backup);
        }

        try {
            // Verify backup integrity first
            if ($backup->checksum) {
                $actualChecksum = hash_file('sha256', $backupPath);
                if ($actualChecksum !== $backup->checksum) {
                    throw new \Exception('Backup integrity check failed. Checksum mismatch.');
                }
            }

            // Create restore directory
            $restoreDir = $this->restorePath . '/' . $backup->id;
            $this->ensureDirectory($restoreDir);

            // Extract backup
            $this->extractBackup($backupPath, $restoreDir);

            // Restore components
            if ($restoreFiles) {
                $this->restoreFiles($account, $restoreDir);
            }

            if ($restoreDatabases) {
                $this->restoreDatabases($account, $restoreDir);
            }

            if ($restoreEmails) {
                $this->restoreEmails($account, $restoreDir);
            }

            if ($restoreConfig) {
                $this->restoreAccountConfig($account, $restoreDir);
            }

            // Clean up
            $this->deleteDirectory($restoreDir);

            return true;
        } catch (\Exception $e) {
            // Clean up on failure
            if (isset($restoreDir) && is_dir($restoreDir)) {
                $this->deleteDirectory($restoreDir);
            }

            throw $e;
        } finally {
            // Clean up decrypted file if created
            if ($backup->is_encrypted && $backupPath !== $backup->path && file_exists($backupPath)) {
                unlink($backupPath);
            }
        }
    }

    /**
     * Selective restore - restore specific files
     */
    public function restoreSpecificFiles(Backup $backup, Account $account, array $files): bool
    {
        $backupPath = $backup->path;

        if ($backup->is_encrypted) {
            $backupPath = $this->decryptBackup($backup);
        }

        try {
            $restoreDir = $this->restorePath . '/' . $backup->id;
            $this->ensureDirectory($restoreDir);

            // Extract only specific files
            foreach ($files as $file) {
                $extractFile = 'files/' . ltrim($file, '/');
                $command = sprintf(
                    'tar -xzf %s -C %s %s 2>/dev/null',
                    escapeshellarg($backupPath),
                    escapeshellarg($restoreDir),
                    escapeshellarg($extractFile)
                );

                exec($command, $output, $returnCode);

                if ($returnCode === 0) {
                    // Copy extracted file to account home
                    $sourcePath = $restoreDir . '/' . $extractFile;
                    $destPath = "/home/{$account->username}/" . ltrim($file, '/');

                    if (file_exists($sourcePath)) {
                        $this->ensureDirectory(dirname($destPath));
                        copy($sourcePath, $destPath);
                        chown($destPath, $account->username);
                        chgrp($destPath, $account->username);
                    }
                }
            }

            $this->deleteDirectory($restoreDir);
            return true;
        } finally {
            if ($backup->is_encrypted && $backupPath !== $backup->path && file_exists($backupPath)) {
                unlink($backupPath);
            }
        }
    }

    /**
     * Restore specific databases
     */
    public function restoreSpecificDatabases(Backup $backup, Account $account, array $databases): bool
    {
        $backupPath = $backup->path;

        if ($backup->is_encrypted) {
            $backupPath = $this->decryptBackup($backup);
        }

        try {
            $restoreDir = $this->restorePath . '/' . $backup->id;
            $this->ensureDirectory($restoreDir);

            // Extract database directory
            $command = sprintf(
                'tar -xzf %s -C %s databases/',
                escapeshellarg($backupPath),
                escapeshellarg($restoreDir)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to extract database backup');
            }

            $dbDir = $restoreDir . '/databases';

            foreach ($databases as $dbName) {
                $this->restoreDatabase($dbName, $dbDir);
            }

            $this->deleteDirectory($restoreDir);
            return true;
        } finally {
            if ($backup->is_encrypted && $backupPath !== $backup->path && file_exists($backupPath)) {
                unlink($backupPath);
            }
        }
    }

    /**
     * Decrypt backup file
     */
    protected function decryptBackup(Backup $backup): string
    {
        if (!$backup->is_encrypted) {
            return $backup->path;
        }

        // Find encryption configuration
        $schedule = $backup->schedule;
        if (!$schedule || !$schedule->encryption) {
            throw new \Exception('Encryption configuration not found');
        }

        $decryptedPath = $this->restorePath . '/decrypted_' . basename($backup->path);
        $this->ensureDirectory($this->restorePath);

        $schedule->encryption->decryptFile($backup->path, $decryptedPath);

        return $decryptedPath;
    }

    /**
     * Extract backup tarball
     */
    protected function extractBackup(string $backupPath, string $destDir): void
    {
        $command = sprintf(
            'tar -xzf %s -C %s',
            escapeshellarg($backupPath),
            escapeshellarg($destDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to extract backup');
        }
    }

    /**
     * Restore files
     */
    protected function restoreFiles(Account $account, string $restoreDir): void
    {
        $filesDir = $restoreDir . '/files';
        $accountHome = "/home/{$account->username}";

        if (!is_dir($filesDir)) {
            return;
        }

        // Backup current files before restore
        $backupCurrent = $accountHome . '_backup_' . time();
        if (is_dir($accountHome)) {
            rename($accountHome, $backupCurrent);
        }

        try {
            // Copy restored files
            $command = sprintf(
                'cp -a %s %s',
                escapeshellarg($filesDir),
                escapeshellarg($accountHome)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                // Restore backup on failure
                if (is_dir($backupCurrent)) {
                    if (is_dir($accountHome)) {
                        $this->deleteDirectory($accountHome);
                    }
                    rename($backupCurrent, $accountHome);
                }
                throw new \Exception('Failed to restore files');
            }

            // Fix permissions
            $this->fixPermissions($accountHome, $account->username);

            // Remove backup of current files
            if (is_dir($backupCurrent)) {
                $this->deleteDirectory($backupCurrent);
            }
        } catch (\Exception $e) {
            // Ensure backup is restored on any error
            if (is_dir($backupCurrent) && !is_dir($accountHome)) {
                rename($backupCurrent, $accountHome);
            }
            throw $e;
        }
    }

    /**
     * Restore databases
     */
    protected function restoreDatabases(Account $account, string $restoreDir): void
    {
        $dbDir = $restoreDir . '/databases';

        if (!is_dir($dbDir)) {
            return;
        }

        $files = scandir($dbDir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $dbDir . '/' . $file;

            if (is_file($filePath)) {
                $dbName = str_replace(['.sql.gz', '.sql'], '', $file);
                $this->restoreDatabase($dbName, $dbDir);
            }
        }
    }

    /**
     * Restore single database
     */
    protected function restoreDatabase(string $dbName, string $dbDir): void
    {
        $sqlFile = $dbDir . '/' . $dbName . '.sql';
        $sqlGzFile = $dbDir . '/' . $dbName . '.sql.gz';

        // Decompress if needed
        if (file_exists($sqlGzFile)) {
            exec("gunzip -c {$sqlGzFile} > {$sqlFile}");
        }

        if (!file_exists($sqlFile)) {
            throw new \Exception("Database dump file not found: {$dbName}");
        }

        // Drop existing database and recreate
        DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        DB::statement("CREATE DATABASE `{$dbName}`");

        // Restore database
        $command = sprintf(
            'mysql %s < %s',
            escapeshellarg($dbName),
            escapeshellarg($sqlFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Failed to restore database: {$dbName}");
        }

        // Clean up decompressed file
        if (file_exists($sqlGzFile)) {
            unlink($sqlFile);
        }
    }

    /**
     * Restore emails
     */
    protected function restoreEmails(Account $account, string $restoreDir): void
    {
        $emailDir = $restoreDir . '/emails';
        $mailDir = "/var/vmail/{$account->domain}";

        if (!is_dir($emailDir)) {
            return;
        }

        // Backup current emails
        $backupCurrent = $mailDir . '_backup_' . time();
        if (is_dir($mailDir)) {
            rename($mailDir, $backupCurrent);
        }

        try {
            $this->ensureDirectory(dirname($mailDir));

            $command = sprintf(
                'cp -a %s %s',
                escapeshellarg($emailDir),
                escapeshellarg($mailDir)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                if (is_dir($backupCurrent)) {
                    rename($backupCurrent, $mailDir);
                }
                throw new \Exception('Failed to restore emails');
            }

            // Fix permissions
            $this->fixPermissions($mailDir, 'vmail');

            // Remove backup
            if (is_dir($backupCurrent)) {
                $this->deleteDirectory($backupCurrent);
            }
        } catch (\Exception $e) {
            if (is_dir($backupCurrent) && !is_dir($mailDir)) {
                rename($backupCurrent, $mailDir);
            }
            throw $e;
        }
    }

    /**
     * Restore account configuration
     */
    protected function restoreAccountConfig(Account $account, string $restoreDir): void
    {
        $configFile = $restoreDir . '/account_config.json';

        if (!file_exists($configFile)) {
            return;
        }

        $config = json_decode(file_get_contents($configFile), true);

        if (!$config) {
            throw new \Exception('Invalid configuration file');
        }

        // Restore account settings (selective)
        if (isset($config['account'])) {
            $account->update([
                'disk_used' => $config['account']['disk_used'] ?? 0,
                'bandwidth_used' => $config['account']['bandwidth_used'] ?? 0,
            ]);
        }

        // Restore DNS zones, email accounts, FTP accounts, etc.
        // This would involve recreating the records in the database
        // Implementation depends on specific requirements
    }

    /**
     * Fix file permissions recursively
     */
    protected function fixPermissions(string $path, string $owner): void
    {
        if (!file_exists($path)) {
            return;
        }

        $command = sprintf(
            'chown -R %s:%s %s',
            escapeshellarg($owner),
            escapeshellarg($owner),
            escapeshellarg($path)
        );

        exec($command);

        // Set appropriate permissions
        exec("find {$path} -type d -exec chmod 755 {} \\;");
        exec("find {$path} -type f -exec chmod 644 {} \\;");
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Delete directory recursively
     */
    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            is_dir($filePath) ? $this->deleteDirectory($filePath) : unlink($filePath);
        }

        rmdir($path);
    }

    /**
     * Download backup from remote location
     */
    public function downloadFromRemote(Backup $backup, int $destinationId): string
    {
        $destination = BackupDestination::findOrFail($destinationId);

        $localPath = $this->restorePath . '/' . $backup->filename;
        $this->ensureDirectory($this->restorePath);

        switch ($destination->type) {
            case 'ftp':
                $this->downloadFromFTP($backup, $destination, $localPath);
                break;

            case 'ssh':
            case 'sftp':
                $this->downloadFromSSH($backup, $destination, $localPath);
                break;

            case 'local':
                $this->downloadFromLocal($backup, $destination, $localPath);
                break;

            default:
                throw new \Exception('Unsupported destination type');
        }

        return $localPath;
    }

    /**
     * Download from FTP
     */
    protected function downloadFromFTP(Backup $backup, BackupDestination $destination, string $localPath): void
    {
        $conn = ftp_connect($destination->hostname, $destination->port ?: 21);
        if (!$conn) {
            throw new \Exception('Could not connect to FTP server');
        }

        if (!ftp_login($conn, $destination->username, $destination->password)) {
            ftp_close($conn);
            throw new \Exception('FTP login failed');
        }

        ftp_pasv($conn, true);

        $remotePath = rtrim($destination->path, '/') . '/' . $backup->filename;

        if (!ftp_get($conn, $localPath, $remotePath, FTP_BINARY)) {
            ftp_close($conn);
            throw new \Exception('FTP download failed');
        }

        ftp_close($conn);
    }

    /**
     * Download from SSH
     */
    protected function downloadFromSSH(Backup $backup, BackupDestination $destination, string $localPath): void
    {
        $remotePath = rtrim($destination->path, '/') . '/' . $backup->filename;

        $command = sprintf(
            'scp -P %d %s@%s:%s %s',
            $destination->port ?: 22,
            escapeshellarg($destination->username),
            escapeshellarg($destination->hostname),
            escapeshellarg($remotePath),
            escapeshellarg($localPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('SCP download failed');
        }
    }

    /**
     * Copy from local destination
     */
    protected function downloadFromLocal(Backup $backup, BackupDestination $destination, string $localPath): void
    {
        $sourcePath = rtrim($destination->path, '/') . '/' . $backup->filename;

        if (!file_exists($sourcePath)) {
            throw new \Exception('Backup file not found in local destination');
        }

        if (!copy($sourcePath, $localPath)) {
            throw new \Exception('Local copy failed');
        }
    }
}
