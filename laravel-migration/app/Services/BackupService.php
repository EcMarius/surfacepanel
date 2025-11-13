<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\BackupDestination;
use App\Models\BackupEncryption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    protected $backupPath = '/var/vp_backups';
    protected $tempPath = '/tmp/vp_backups';

    /**
     * Create a full backup for an account
     */
    public function createFullBackup(Account $account, BackupSchedule $schedule = null): Backup
    {
        $backup = Backup::create([
            'account_id' => $account->id,
            'schedule_id' => $schedule?->id,
            'filename' => $this->generateBackupFilename($account, 'full'),
            'type' => 'full',
            'status' => 'pending',
        ]);

        try {
            $backup->markAsStarted();

            // Create backup directory
            $backupDir = $this->tempPath . '/' . $backup->id;
            $this->ensureDirectory($backupDir);

            // Backup files
            $this->backupFiles($account, $backupDir);

            // Backup databases
            $this->backupDatabases($account, $backupDir);

            // Backup emails
            $this->backupEmails($account, $backupDir);

            // Backup account configuration
            $this->backupAccountConfig($account, $backupDir);

            // Create tarball
            $tarballPath = $this->createTarball($backupDir, $backup->filename);

            // Calculate checksum
            $checksum = hash_file('sha256', $tarballPath);

            // Move to permanent location
            $finalPath = $this->backupPath . '/' . $backup->filename;
            $this->ensureDirectory($this->backupPath);
            rename($tarballPath, $finalPath);

            // Clean up temp directory
            $this->deleteDirectory($backupDir);

            // Get file size
            $size = filesize($finalPath);

            // Encrypt if needed
            if ($schedule && $schedule->is_encrypted && $schedule->encryption) {
                $finalPath = $this->encryptBackup($finalPath, $schedule->encryption);
                $size = filesize($finalPath);
                $backup->is_encrypted = true;
                $backup->encryption_method = $schedule->encryption->method;
            }

            $backup->path = $finalPath;
            $backup->markAsCompleted($size, $checksum);

            // Upload to remote destinations if specified
            if ($schedule && $schedule->destination_ids) {
                $this->uploadToRemote($backup, $schedule->destinations());
            }

            // Apply rotation policy
            if ($schedule && $schedule->rotation) {
                $this->applyRotation($account, $schedule->rotation);
            }

            return $backup;
        } catch (\Exception $e) {
            $backup->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Create an incremental backup
     */
    public function createIncrementalBackup(Account $account, BackupSchedule $schedule = null): Backup
    {
        $backup = Backup::create([
            'account_id' => $account->id,
            'schedule_id' => $schedule?->id,
            'filename' => $this->generateBackupFilename($account, 'incremental'),
            'type' => 'incremental',
            'status' => 'pending',
        ]);

        try {
            $backup->markAsStarted();

            // Get last full backup
            $lastBackup = Backup::where('account_id', $account->id)
                ->where('type', 'full')
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastBackup) {
                throw new \Exception('No full backup found. Please create a full backup first.');
            }

            $backupDir = $this->tempPath . '/' . $backup->id;
            $this->ensureDirectory($backupDir);

            // Use rsync for incremental backup
            $this->incrementalBackupFiles($account, $backupDir, $lastBackup->created_at);

            // Backup changed databases
            $this->backupDatabases($account, $backupDir);

            // Create tarball
            $tarballPath = $this->createTarball($backupDir, $backup->filename);
            $checksum = hash_file('sha256', $tarballPath);

            $finalPath = $this->backupPath . '/' . $backup->filename;
            rename($tarballPath, $finalPath);

            $this->deleteDirectory($backupDir);

            $size = filesize($finalPath);

            if ($schedule && $schedule->is_encrypted && $schedule->encryption) {
                $finalPath = $this->encryptBackup($finalPath, $schedule->encryption);
                $size = filesize($finalPath);
                $backup->is_encrypted = true;
                $backup->encryption_method = $schedule->encryption->method;
            }

            $backup->path = $finalPath;
            $backup->markAsCompleted($size, $checksum);

            if ($schedule && $schedule->destination_ids) {
                $this->uploadToRemote($backup, $schedule->destinations());
            }

            return $backup;
        } catch (\Exception $e) {
            $backup->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Backup account files
     */
    protected function backupFiles(Account $account, string $backupDir): void
    {
        $accountHome = "/home/{$account->username}";
        $filesDir = $backupDir . '/files';

        $this->ensureDirectory($filesDir);

        // Use rsync for efficient copying
        $command = sprintf(
            'rsync -a --exclude=tmp --exclude=cache %s/ %s/',
            escapeshellarg($accountHome),
            escapeshellarg($filesDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to backup files');
        }
    }

    /**
     * Incremental backup using rsync
     */
    protected function incrementalBackupFiles(Account $account, string $backupDir, $since): void
    {
        $accountHome = "/home/{$account->username}";
        $filesDir = $backupDir . '/files';

        $this->ensureDirectory($filesDir);

        // Find files modified after last backup
        $findCommand = sprintf(
            'find %s -type f -newermt "%s" -not -path "*/tmp/*" -not -path "*/cache/*"',
            escapeshellarg($accountHome),
            $since->format('Y-m-d H:i:s')
        );

        exec($findCommand, $files, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to find changed files');
        }

        // Copy changed files
        foreach ($files as $file) {
            $relativePath = str_replace($accountHome . '/', '', $file);
            $destPath = $filesDir . '/' . $relativePath;
            $destDir = dirname($destPath);

            $this->ensureDirectory($destDir);
            copy($file, $destPath);
        }
    }

    /**
     * Backup databases
     */
    protected function backupDatabases(Account $account, string $backupDir): void
    {
        $dbDir = $backupDir . '/databases';
        $this->ensureDirectory($dbDir);

        foreach ($account->databases as $database) {
            $dumpFile = $dbDir . '/' . $database->database_name . '.sql';

            $command = sprintf(
                'mysqldump --single-transaction --quick --lock-tables=false %s > %s',
                escapeshellarg($database->database_name),
                escapeshellarg($dumpFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Failed to backup database: {$database->database_name}");
            }

            // Compress SQL dump
            exec("gzip {$dumpFile}");
        }
    }

    /**
     * Backup emails
     */
    protected function backupEmails(Account $account, string $backupDir): void
    {
        $emailDir = $backupDir . '/emails';
        $this->ensureDirectory($emailDir);

        $mailDir = "/var/vmail/{$account->domain}";

        if (is_dir($mailDir)) {
            $command = sprintf(
                'rsync -a %s/ %s/',
                escapeshellarg($mailDir),
                escapeshellarg($emailDir)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to backup emails');
            }
        }
    }

    /**
     * Backup account configuration
     */
    protected function backupAccountConfig(Account $account, string $backupDir): void
    {
        $configFile = $backupDir . '/account_config.json';

        $config = [
            'account' => $account->toArray(),
            'package' => $account->package->toArray(),
            'databases' => $account->databases->toArray(),
            'email_accounts' => $account->emailAccounts->toArray(),
            'ftp_accounts' => $account->ftpAccounts->toArray(),
            'dns_zones' => $account->dnsZones->toArray(),
            'ssl_certificates' => $account->sslCertificates->toArray(),
            'cron_jobs' => $account->cronJobs->toArray(),
        ];

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Create tarball from directory
     */
    protected function createTarball(string $sourceDir, string $filename): string
    {
        $tarballPath = $this->tempPath . '/' . $filename;

        $command = sprintf(
            'tar -czf %s -C %s .',
            escapeshellarg($tarballPath),
            escapeshellarg($sourceDir)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tarballPath)) {
            throw new \Exception('Failed to create tarball');
        }

        return $tarballPath;
    }

    /**
     * Encrypt backup file
     */
    protected function encryptBackup(string $backupPath, BackupEncryption $encryption): string
    {
        $encryptedPath = $backupPath . '.enc';

        $encryption->encryptFile($backupPath, $encryptedPath);

        // Remove unencrypted backup
        unlink($backupPath);

        return $encryptedPath;
    }

    /**
     * Upload backup to remote destinations
     */
    protected function uploadToRemote(Backup $backup, $destinations): void
    {
        $uploadedTo = [];

        foreach ($destinations as $destination) {
            if (!$destination->is_active) {
                continue;
            }

            try {
                switch ($destination->type) {
                    case 's3':
                        $this->uploadToS3($backup, $destination);
                        break;

                    case 'ftp':
                        $this->uploadToFTP($backup, $destination);
                        break;

                    case 'sftp':
                    case 'ssh':
                        $this->uploadToSSH($backup, $destination);
                        break;

                    case 'local':
                        $this->uploadToLocal($backup, $destination);
                        break;
                }

                $uploadedTo[] = $destination->id;
            } catch (\Exception $e) {
                // Log error but continue with other destinations
                \Log::error("Failed to upload backup {$backup->id} to destination {$destination->id}: " . $e->getMessage());
            }
        }

        if (!empty($uploadedTo)) {
            $backup->markAsUploaded($uploadedTo);
        }
    }

    /**
     * Upload to S3
     */
    protected function uploadToS3(Backup $backup, BackupDestination $destination): void
    {
        // Implementation would use AWS SDK
        // This is a placeholder
        throw new \Exception('S3 upload not yet implemented');
    }

    /**
     * Upload to FTP
     */
    protected function uploadToFTP(Backup $backup, BackupDestination $destination): void
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

        if (!ftp_put($conn, $remotePath, $backup->path, FTP_BINARY)) {
            ftp_close($conn);
            throw new \Exception('FTP upload failed');
        }

        ftp_close($conn);
    }

    /**
     * Upload to SSH/SFTP
     */
    protected function uploadToSSH(Backup $backup, BackupDestination $destination): void
    {
        $remotePath = rtrim($destination->path, '/') . '/' . $backup->filename;

        $command = sprintf(
            'scp -P %d %s %s@%s:%s',
            $destination->port ?: 22,
            escapeshellarg($backup->path),
            escapeshellarg($destination->username),
            escapeshellarg($destination->hostname),
            escapeshellarg($remotePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('SCP upload failed');
        }
    }

    /**
     * Copy to local destination
     */
    protected function uploadToLocal(Backup $backup, BackupDestination $destination): void
    {
        $this->ensureDirectory($destination->path);
        $destPath = rtrim($destination->path, '/') . '/' . $backup->filename;

        if (!copy($backup->path, $destPath)) {
            throw new \Exception('Local copy failed');
        }
    }

    /**
     * Apply rotation policy
     */
    protected function applyRotation(Account $account, $rotation): void
    {
        // Get expired backups
        $expiredBackups = $rotation->getExpiredBackups($account->id);

        foreach ($expiredBackups as $backup) {
            $this->deleteBackup($backup, $rotation->delete_from_remote);
        }

        // Check storage limits
        if ($rotation->isStorageLimitExceeded($account->id)) {
            $toRemove = $rotation->getBackupsToRotate($account->id, 5);
            foreach ($toRemove as $backup) {
                $this->deleteBackup($backup, $rotation->delete_from_remote);
            }
        }

        // Check count limits
        if ($rotation->isBackupCountExceeded($account->id)) {
            $toRemove = $rotation->getBackupsToRotate($account->id, 5);
            foreach ($toRemove as $backup) {
                $this->deleteBackup($backup, $rotation->delete_from_remote);
            }
        }
    }

    /**
     * Delete a backup
     */
    protected function deleteBackup(Backup $backup, bool $deleteFromRemote = true): void
    {
        // Delete local file
        $backup->deleteFile();

        // Delete from remote if specified
        if ($deleteFromRemote && $backup->is_remote && $backup->remote_locations) {
            foreach ($backup->remote_locations as $destinationId) {
                $destination = BackupDestination::find($destinationId);
                if ($destination) {
                    $this->deleteFromRemote($backup, $destination);
                }
            }
        }

        // Delete database record
        $backup->delete();
    }

    /**
     * Delete backup from remote destination
     */
    protected function deleteFromRemote(Backup $backup, BackupDestination $destination): void
    {
        try {
            switch ($destination->type) {
                case 'ftp':
                    $this->deleteFromFTP($backup, $destination);
                    break;

                case 'ssh':
                case 'sftp':
                    $this->deleteFromSSH($backup, $destination);
                    break;

                case 'local':
                    $this->deleteFromLocal($backup, $destination);
                    break;
            }
        } catch (\Exception $e) {
            \Log::error("Failed to delete backup from remote: " . $e->getMessage());
        }
    }

    /**
     * Delete from FTP
     */
    protected function deleteFromFTP(Backup $backup, BackupDestination $destination): void
    {
        $conn = ftp_connect($destination->hostname, $destination->port ?: 21);
        if ($conn && ftp_login($conn, $destination->username, $destination->password)) {
            $remotePath = rtrim($destination->path, '/') . '/' . $backup->filename;
            ftp_delete($conn, $remotePath);
            ftp_close($conn);
        }
    }

    /**
     * Delete from SSH
     */
    protected function deleteFromSSH(Backup $backup, BackupDestination $destination): void
    {
        $remotePath = rtrim($destination->path, '/') . '/' . $backup->filename;

        $command = sprintf(
            'ssh -p %d %s@%s "rm -f %s"',
            $destination->port ?: 22,
            escapeshellarg($destination->username),
            escapeshellarg($destination->hostname),
            escapeshellarg($remotePath)
        );

        exec($command);
    }

    /**
     * Delete from local destination
     */
    protected function deleteFromLocal(Backup $backup, BackupDestination $destination): void
    {
        $localPath = rtrim($destination->path, '/') . '/' . $backup->filename;
        if (file_exists($localPath)) {
            unlink($localPath);
        }
    }

    /**
     * Generate backup filename
     */
    protected function generateBackupFilename(Account $account, string $type): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "{$account->username}_{$type}_{$timestamp}.tar.gz";
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
     * Verify backup integrity
     */
    public function verifyBackup(Backup $backup): bool
    {
        if (!$backup->verify()) {
            return false;
        }

        $backup->markAsVerified();
        return true;
    }
}
