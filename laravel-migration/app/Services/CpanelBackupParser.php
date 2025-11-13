<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CpanelBackupParser
{
    protected string $extractPath;
    protected string $backupPath;
    protected array $parsedData = [];

    /**
     * Initialize the parser with backup path
     */
    public function __construct(string $backupPath)
    {
        $this->backupPath = $backupPath;
        $this->extractPath = storage_path('app/migrations/extracted/' . basename($backupPath, '.tar.gz'));
    }

    /**
     * Extract the cPanel backup file
     */
    public function extract(): bool
    {
        try {
            // Create extraction directory
            if (!File::exists($this->extractPath)) {
                File::makeDirectory($this->extractPath, 0755, true);
            }

            // Extract tar.gz file
            $command = sprintf(
                'tar -xzf %s -C %s 2>&1',
                escapeshellarg($this->backupPath),
                escapeshellarg($this->extractPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception('Failed to extract backup: ' . implode("\n", $output));
            }

            return true;
        } catch (Exception $e) {
            Log::error('Backup extraction failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse the entire cPanel backup
     */
    public function parse(): array
    {
        $this->parsedData = [
            'account' => $this->parseAccount(),
            'domains' => $this->parseDomains(),
            'subdomains' => $this->parseSubdomains(),
            'addon_domains' => $this->parseAddonDomains(),
            'parked_domains' => $this->parseParkedDomains(),
            'email_accounts' => $this->parseEmailAccounts(),
            'email_forwarders' => $this->parseEmailForwarders(),
            'databases' => $this->parseDatabases(),
            'database_users' => $this->parseDatabaseUsers(),
            'dns_zones' => $this->parseDnsZones(),
            'ssl_certificates' => $this->parseSSLCertificates(),
            'cron_jobs' => $this->parseCronJobs(),
            'ftp_accounts' => $this->parseFtpAccounts(),
        ];

        return $this->parsedData;
    }

    /**
     * Parse main account information
     */
    protected function parseAccount(): array
    {
        $cpUserFile = $this->findFile('cp/cp*');
        if (!$cpUserFile) {
            return [];
        }

        $data = [];
        $lines = file($cpUserFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }

        return [
            'username' => $data['USER'] ?? '',
            'domain' => $data['DNS'] ?? $data['DOMAIN'] ?? '',
            'email' => $data['CONTACTEMAIL'] ?? '',
            'primary_ip' => $data['IP'] ?? '',
            'plan' => $data['PLAN'] ?? 'default',
            'disk_quota' => isset($data['DISKQUOTA']) ? (int)$data['DISKQUOTA'] : -1,
            'bandwidth_quota' => isset($data['BWLIMIT']) ? (int)$data['BWLIMIT'] : -1,
        ];
    }

    /**
     * Parse domains
     */
    protected function parseDomains(): array
    {
        $domains = [];
        $userdataPath = $this->extractPath . '/userdata';

        if (File::exists($userdataPath)) {
            $files = File::files($userdataPath);
            foreach ($files as $file) {
                $domain = $this->parseUserdataFile($file->getPathname());
                if ($domain) {
                    $domains[] = $domain;
                }
            }
        }

        return $domains;
    }

    /**
     * Parse userdata file for domain information
     */
    protected function parseUserdataFile(string $filePath): ?array
    {
        if (!File::exists($filePath) || basename($filePath) === 'main') {
            return null;
        }

        $data = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }

        return [
            'domain' => basename($filePath),
            'document_root' => $data['documentroot'] ?? '',
            'server_alias' => $data['serveralias'] ?? '',
            'user' => $data['user'] ?? '',
        ];
    }

    /**
     * Parse subdomains
     */
    protected function parseSubdomains(): array
    {
        $subdomains = [];
        $userdataPath = $this->extractPath . '/userdata';

        if (File::exists($userdataPath)) {
            $files = File::files($userdataPath);
            foreach ($files as $file) {
                $filename = basename($file->getPathname());
                if (strpos($filename, '.') !== false && $filename !== 'main') {
                    $data = $this->parseUserdataFile($file->getPathname());
                    if ($data) {
                        $parts = explode('.', $filename);
                        $subdomains[] = [
                            'subdomain' => $parts[0],
                            'parent_domain' => implode('.', array_slice($parts, 1)),
                            'document_root' => $data['document_root'],
                        ];
                    }
                }
            }
        }

        return $subdomains;
    }

    /**
     * Parse addon domains
     */
    protected function parseAddonDomains(): array
    {
        // Addon domains are in addons file
        $addonsFile = $this->findFile('addons');
        if (!$addonsFile || !File::exists($addonsFile)) {
            return [];
        }

        $addonDomains = [];
        $lines = file($addonsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($domain, $docroot) = explode('=', $line, 2);
                $addonDomains[] = [
                    'domain' => trim($domain),
                    'document_root' => trim($docroot),
                ];
            }
        }

        return $addonDomains;
    }

    /**
     * Parse parked domains
     */
    protected function parseParkedDomains(): array
    {
        $parkedFile = $this->findFile('parked_domains');
        if (!$parkedFile || !File::exists($parkedFile)) {
            return [];
        }

        $parkedDomains = [];
        $lines = file($parkedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($domain, $target) = explode('=', $line, 2);
                $parkedDomains[] = [
                    'domain' => trim($domain),
                    'target_domain' => trim($target),
                ];
            }
        }

        return $parkedDomains;
    }

    /**
     * Parse email accounts
     */
    protected function parseEmailAccounts(): array
    {
        $emailAccounts = [];
        $mailPath = $this->extractPath . '/mail';

        if (!File::exists($mailPath)) {
            return [];
        }

        // Parse shadow file for encrypted passwords
        $shadowFile = $mailPath . '/shadow';
        $passwords = [];
        if (File::exists($shadowFile)) {
            $lines = file($shadowFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($email, $password) = explode(':', $line, 2);
                    $passwords[$email] = $password;
                }
            }
        }

        // Parse passwd file for email accounts
        $passwdFile = $mailPath . '/passwd';
        if (File::exists($passwdFile)) {
            $lines = file($passwdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 6) {
                    $email = $parts[0];
                    $emailAccounts[] = [
                        'email' => $email,
                        'password' => $passwords[$email] ?? '',
                        'quota' => isset($parts[5]) ? (int)$parts[5] : 250,
                    ];
                }
            }
        }

        return $emailAccounts;
    }

    /**
     * Parse email forwarders
     */
    protected function parseEmailForwarders(): array
    {
        $forwarders = [];
        $forwardersFile = $this->findFile('forwarders');

        if ($forwardersFile && File::exists($forwardersFile)) {
            $lines = file($forwardersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($source, $destination) = explode(':', $line, 2);
                    $forwarders[] = [
                        'source' => trim($source),
                        'destination' => trim($destination),
                    ];
                }
            }
        }

        return $forwarders;
    }

    /**
     * Parse MySQL databases
     */
    protected function parseDatabases(): array
    {
        $databases = [];
        $mysqlPath = $this->extractPath . '/mysql';

        if (File::exists($mysqlPath)) {
            $files = File::files($mysqlPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'sql') {
                    $databases[] = [
                        'database_name' => basename($file->getFilename(), '.sql'),
                        'sql_file' => $file->getPathname(),
                        'size' => $file->getSize(),
                    ];
                }
            }
        }

        return $databases;
    }

    /**
     * Parse database users
     */
    protected function parseDatabaseUsers(): array
    {
        $dbUsers = [];
        $mysqlUserFile = $this->findFile('mysql.sql');

        if ($mysqlUserFile && File::exists($mysqlUserFile)) {
            $content = File::get($mysqlUserFile);
            // Parse GRANT statements to extract users
            preg_match_all("/GRANT.*TO '([^']+)'@'([^']+)' IDENTIFIED BY PASSWORD '([^']+)'/", $content, $matches);

            for ($i = 0; $i < count($matches[1]); $i++) {
                $dbUsers[] = [
                    'username' => $matches[1][$i],
                    'host' => $matches[2][$i],
                    'password' => $matches[3][$i],
                ];
            }
        }

        return $dbUsers;
    }

    /**
     * Parse DNS zones
     */
    protected function parseDnsZones(): array
    {
        $zones = [];
        $dnsPath = $this->extractPath . '/dnszones';

        if (File::exists($dnsPath)) {
            $files = File::files($dnsPath);
            foreach ($files as $file) {
                $zones[] = [
                    'domain' => basename($file->getFilename()),
                    'zone_file' => $file->getPathname(),
                    'content' => File::get($file->getPathname()),
                ];
            }
        }

        return $zones;
    }

    /**
     * Parse SSL certificates
     */
    protected function parseSSLCertificates(): array
    {
        $certificates = [];
        $sslPath = $this->extractPath . '/ssl';

        if (File::exists($sslPath)) {
            // Parse SSL files
            $certFiles = File::glob($sslPath . '/*.crt');
            foreach ($certFiles as $certFile) {
                $domain = basename($certFile, '.crt');
                $keyFile = $sslPath . '/' . $domain . '.key';
                $cabundleFile = $sslPath . '/' . $domain . '.cabundle';

                if (File::exists($keyFile)) {
                    $certificates[] = [
                        'domain' => $domain,
                        'certificate' => File::get($certFile),
                        'private_key' => File::get($keyFile),
                        'ca_bundle' => File::exists($cabundleFile) ? File::get($cabundleFile) : null,
                    ];
                }
            }
        }

        return $certificates;
    }

    /**
     * Parse cron jobs
     */
    protected function parseCronJobs(): array
    {
        $cronJobs = [];
        $cronFile = $this->findFile('cron');

        if ($cronFile && File::exists($cronFile)) {
            $lines = file($cronFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                // Parse cron line
                $parts = preg_split('/\s+/', $line, 6);
                if (count($parts) === 6) {
                    $cronJobs[] = [
                        'schedule' => implode(' ', array_slice($parts, 0, 5)),
                        'command' => $parts[5],
                    ];
                }
            }
        }

        return $cronJobs;
    }

    /**
     * Parse FTP accounts
     */
    protected function parseFtpAccounts(): array
    {
        $ftpAccounts = [];
        $ftpFile = $this->findFile('proftpdpasswd');

        if ($ftpFile && File::exists($ftpFile)) {
            $lines = file($ftpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 7) {
                    $ftpAccounts[] = [
                        'username' => $parts[0],
                        'password' => $parts[1],
                        'uid' => (int)$parts[2],
                        'gid' => (int)$parts[3],
                        'home_directory' => $parts[5],
                    ];
                }
            }
        }

        return $ftpAccounts;
    }

    /**
     * Find a file in the extracted backup
     */
    protected function findFile(string $pattern): ?string
    {
        $files = File::glob($this->extractPath . '/' . $pattern);
        return !empty($files) ? $files[0] : null;
    }

    /**
     * Get the extracted path
     */
    public function getExtractPath(): string
    {
        return $this->extractPath;
    }

    /**
     * Clean up extracted files
     */
    public function cleanup(): void
    {
        if (File::exists($this->extractPath)) {
            File::deleteDirectory($this->extractPath);
        }
    }

    /**
     * Get parsed data
     */
    public function getParsedData(): array
    {
        return $this->parsedData;
    }

    /**
     * Validate backup structure
     */
    public function validate(): array
    {
        $errors = [];

        // Check if main account file exists
        if (!$this->findFile('cp/cp*')) {
            $errors[] = 'Missing cPanel account file (cp/*)';
        }

        // Check if userdata directory exists
        if (!File::exists($this->extractPath . '/userdata')) {
            $errors[] = 'Missing userdata directory';
        }

        return $errors;
    }
}
