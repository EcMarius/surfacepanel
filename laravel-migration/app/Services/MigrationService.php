<?php

namespace App\Services;

use App\Models\Migration;
use App\Models\MigrationLog;
use App\Models\Account;
use App\Models\User;
use App\Models\Package;
use App\Models\AddonDomain;
use App\Models\Subdomain;
use App\Models\ParkedDomain;
use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\Database;
use App\Models\DatabaseUser;
use App\Models\DnsZone;
use App\Models\DnsRecord;
use App\Models\SslCertificate;
use App\Models\CronJob;
use App\Models\FtpAccount;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrationService
{
    protected Migration $migration;
    protected CpanelBackupParser $parser;
    protected array $migratedData = [];

    /**
     * Initialize migration service
     */
    public function __construct(Migration $migration)
    {
        $this->migration = $migration;
        $this->parser = new CpanelBackupParser($migration->backup_path);
    }

    /**
     * Start the migration process
     */
    public function migrate(): bool
    {
        try {
            DB::beginTransaction();

            // Update migration status
            $this->updateStatus('parsing', 5, 'Extracting backup file...');

            // Extract backup
            if (!$this->parser->extract()) {
                throw new Exception('Failed to extract backup file');
            }

            $this->log('success', null, null, 'Backup extracted successfully');

            // Parse backup
            $this->updateStatus('parsing', 10, 'Parsing backup structure...');
            $parsedData = $this->parser->parse();

            // Validate parsed data
            $validationErrors = $this->validateParsedData($parsedData);
            if (!empty($validationErrors)) {
                $this->migration->validation_errors = $validationErrors;
                $this->migration->save();
                throw new Exception('Backup validation failed');
            }

            // Store migration summary
            $this->migration->migration_summary = $this->buildMigrationSummary($parsedData);
            $this->migration->save();

            $this->updateStatus('processing', 15, 'Creating account...');

            // Migrate account
            $account = $this->migrateAccount($parsedData['account']);
            if (!$account) {
                throw new Exception('Failed to create account');
            }

            $this->migratedData['account'] = $account;

            // Migrate domains
            $this->updateStatus('processing', 25, 'Migrating addon domains...');
            $this->migrateAddonDomains($account, $parsedData['addon_domains'] ?? []);

            // Migrate subdomains
            $this->updateStatus('processing', 35, 'Migrating subdomains...');
            $this->migrateSubdomains($account, $parsedData['subdomains'] ?? []);

            // Migrate parked domains
            $this->updateStatus('processing', 40, 'Migrating parked domains...');
            $this->migrateParkedDomains($account, $parsedData['parked_domains'] ?? []);

            // Migrate email accounts
            $this->updateStatus('processing', 50, 'Migrating email accounts...');
            $this->migrateEmailAccounts($account, $parsedData['email_accounts'] ?? []);

            // Migrate email forwarders
            $this->updateStatus('processing', 60, 'Migrating email forwarders...');
            $this->migrateEmailForwarders($account, $parsedData['email_forwarders'] ?? []);

            // Migrate databases
            $this->updateStatus('processing', 70, 'Migrating databases...');
            $this->migrateDatabases($account, $parsedData['databases'] ?? []);

            // Migrate database users
            $this->updateStatus('processing', 75, 'Migrating database users...');
            $this->migrateDatabaseUsers($account, $parsedData['database_users'] ?? []);

            // Migrate DNS zones
            $this->updateStatus('processing', 80, 'Migrating DNS zones...');
            $this->migrateDnsZones($account, $parsedData['dns_zones'] ?? []);

            // Migrate SSL certificates
            $this->updateStatus('processing', 85, 'Migrating SSL certificates...');
            $this->migrateSSLCertificates($account, $parsedData['ssl_certificates'] ?? []);

            // Migrate cron jobs
            $this->updateStatus('processing', 90, 'Migrating cron jobs...');
            $this->migrateCronJobs($account, $parsedData['cron_jobs'] ?? []);

            // Migrate FTP accounts
            $this->updateStatus('processing', 95, 'Migrating FTP accounts...');
            $this->migrateFtpAccounts($account, $parsedData['ftp_accounts'] ?? []);

            // Complete migration
            $this->updateStatus('completed', 100, 'Migration completed successfully');
            $this->migration->migration_results = $this->buildMigrationResults();
            $this->migration->completed_at = now();
            $this->migration->save();

            DB::commit();

            // Cleanup
            $this->parser->cleanup();

            $this->log('success', null, null, 'Migration completed successfully');

            return true;
        } catch (Exception $e) {
            DB::rollBack();

            $this->updateStatus('failed', $this->migration->progress_percentage, 'Migration failed');
            $this->migration->error_message = $e->getMessage();
            $this->migration->save();

            $this->log('error', null, null, 'Migration failed: ' . $e->getMessage());

            Log::error('Migration failed: ' . $e->getMessage(), [
                'migration_id' => $this->migration->id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Cleanup on failure
            $this->parser->cleanup();

            return false;
        }
    }

    /**
     * Migrate account
     */
    protected function migrateAccount(array $accountData): ?Account
    {
        try {
            // Find or create package
            $package = Package::firstOrCreate(
                ['name' => $accountData['plan'] ?? 'Default'],
                [
                    'description' => 'Migrated from cPanel',
                    'disk_quota' => $accountData['disk_quota'] ?? -1,
                    'bandwidth_quota' => $accountData['bandwidth_quota'] ?? -1,
                    'email_accounts' => -1,
                    'databases' => -1,
                    'ftp_accounts' => -1,
                    'addon_domains' => -1,
                    'subdomains' => -1,
                    'parked_domains' => -1,
                    'monthly_price' => 0,
                    'is_active' => true,
                ]
            );

            // Create user
            $user = User::create([
                'email' => $accountData['email'] ?: $accountData['username'] . '@' . $accountData['domain'],
                'username' => $accountData['username'],
                'password' => Hash::make(Str::random(16)), // Generate random password
                'role' => 'user',
                'status' => 'active',
            ]);

            // Create account
            $account = Account::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'username' => $accountData['username'],
                'domain' => $accountData['domain'],
                'primary_ip' => $accountData['primary_ip'] ?: '0.0.0.0',
                'status' => 'active',
                'disk_used' => 0,
                'bandwidth_used' => 0,
            ]);

            $this->log('success', 'account', $accountData['username'], 'Account created successfully');

            return $account;
        } catch (Exception $e) {
            $this->log('error', 'account', $accountData['username'] ?? 'unknown', 'Failed to create account: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Migrate addon domains
     */
    protected function migrateAddonDomains(Account $account, array $domains): void
    {
        foreach ($domains as $domainData) {
            try {
                AddonDomain::create([
                    'account_id' => $account->id,
                    'domain' => $domainData['domain'],
                    'document_root' => $domainData['document_root'],
                ]);

                $this->log('success', 'domain', $domainData['domain'], 'Addon domain migrated successfully');
                $this->migratedData['addon_domains'][] = $domainData['domain'];
            } catch (Exception $e) {
                $this->log('error', 'domain', $domainData['domain'], 'Failed to migrate addon domain: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate subdomains
     */
    protected function migrateSubdomains(Account $account, array $subdomains): void
    {
        foreach ($subdomains as $subdomainData) {
            try {
                Subdomain::create([
                    'account_id' => $account->id,
                    'subdomain' => $subdomainData['subdomain'],
                    'parent_domain' => $subdomainData['parent_domain'],
                    'document_root' => $subdomainData['document_root'],
                ]);

                $fullDomain = $subdomainData['subdomain'] . '.' . $subdomainData['parent_domain'];
                $this->log('success', 'domain', $fullDomain, 'Subdomain migrated successfully');
                $this->migratedData['subdomains'][] = $fullDomain;
            } catch (Exception $e) {
                $this->log('error', 'domain', $subdomainData['subdomain'], 'Failed to migrate subdomain: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate parked domains
     */
    protected function migrateParkedDomains(Account $account, array $domains): void
    {
        foreach ($domains as $domainData) {
            try {
                ParkedDomain::create([
                    'account_id' => $account->id,
                    'domain' => $domainData['domain'],
                    'target_domain' => $domainData['target_domain'],
                ]);

                $this->log('success', 'domain', $domainData['domain'], 'Parked domain migrated successfully');
                $this->migratedData['parked_domains'][] = $domainData['domain'];
            } catch (Exception $e) {
                $this->log('error', 'domain', $domainData['domain'], 'Failed to migrate parked domain: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate email accounts
     */
    protected function migrateEmailAccounts(Account $account, array $emails): void
    {
        foreach ($emails as $emailData) {
            try {
                EmailAccount::create([
                    'account_id' => $account->id,
                    'email' => $emailData['email'],
                    'password' => $emailData['password'], // Already hashed from cPanel
                    'quota' => $emailData['quota'] ?? 250,
                    'used' => 0,
                    'status' => 'active',
                ]);

                $this->log('success', 'email', $emailData['email'], 'Email account migrated successfully');
                $this->migratedData['email_accounts'][] = $emailData['email'];
            } catch (Exception $e) {
                $this->log('error', 'email', $emailData['email'], 'Failed to migrate email account: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate email forwarders
     */
    protected function migrateEmailForwarders(Account $account, array $forwarders): void
    {
        foreach ($forwarders as $forwarderData) {
            try {
                EmailForwarder::create([
                    'account_id' => $account->id,
                    'source' => $forwarderData['source'],
                    'destination' => $forwarderData['destination'],
                ]);

                $this->log('success', 'email', $forwarderData['source'], 'Email forwarder migrated successfully');
                $this->migratedData['email_forwarders'][] = $forwarderData['source'];
            } catch (Exception $e) {
                $this->log('error', 'email', $forwarderData['source'], 'Failed to migrate email forwarder: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate databases
     */
    protected function migrateDatabases(Account $account, array $databases): void
    {
        foreach ($databases as $dbData) {
            try {
                $database = Database::create([
                    'account_id' => $account->id,
                    'database_name' => $dbData['database_name'],
                    'size' => round($dbData['size'] / 1048576, 2), // Convert bytes to MB
                ]);

                // Import SQL dump if exists
                if (isset($dbData['sql_file']) && file_exists($dbData['sql_file'])) {
                    // TODO: Execute SQL import
                    // This should be handled by a separate service or queue job
                    $this->log('info', 'database', $dbData['database_name'], 'SQL dump found, ready for import');
                }

                $this->log('success', 'database', $dbData['database_name'], 'Database migrated successfully');
                $this->migratedData['databases'][] = $dbData['database_name'];
            } catch (Exception $e) {
                $this->log('error', 'database', $dbData['database_name'], 'Failed to migrate database: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate database users
     */
    protected function migrateDatabaseUsers(Account $account, array $users): void
    {
        foreach ($users as $userData) {
            try {
                DatabaseUser::create([
                    'account_id' => $account->id,
                    'username' => $userData['username'],
                    'password' => $userData['password'], // Already hashed
                ]);

                $this->log('success', 'database', $userData['username'], 'Database user migrated successfully');
                $this->migratedData['database_users'][] = $userData['username'];
            } catch (Exception $e) {
                $this->log('error', 'database', $userData['username'], 'Failed to migrate database user: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate DNS zones
     */
    protected function migrateDnsZones(Account $account, array $zones): void
    {
        foreach ($zones as $zoneData) {
            try {
                // Parse zone file
                $zoneContent = $zoneData['content'];
                $records = $this->parseDnsZoneFile($zoneContent);

                $dnsZone = DnsZone::create([
                    'account_id' => $account->id,
                    'domain' => $zoneData['domain'],
                    'soa' => $records['soa'] ?? 'ns1.virpanel.local',
                    'serial' => time(),
                    'status' => 'active',
                ]);

                // Create DNS records
                foreach ($records['records'] ?? [] as $record) {
                    DnsRecord::create([
                        'zone_id' => $dnsZone->id,
                        'name' => $record['name'],
                        'type' => $record['type'],
                        'value' => $record['value'],
                        'ttl' => $record['ttl'] ?? 3600,
                        'priority' => $record['priority'] ?? null,
                    ]);
                }

                $this->log('success', 'dns', $zoneData['domain'], 'DNS zone migrated successfully');
                $this->migratedData['dns_zones'][] = $zoneData['domain'];
            } catch (Exception $e) {
                $this->log('error', 'dns', $zoneData['domain'], 'Failed to migrate DNS zone: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate SSL certificates
     */
    protected function migrateSSLCertificates(Account $account, array $certificates): void
    {
        foreach ($certificates as $certData) {
            try {
                // Parse certificate to get expiration date
                $expiresAt = $this->parseCertificateExpiry($certData['certificate']);

                SslCertificate::create([
                    'account_id' => $account->id,
                    'domain' => $certData['domain'],
                    'certificate' => $certData['certificate'],
                    'private_key' => $certData['private_key'],
                    'ca_bundle' => $certData['ca_bundle'],
                    'type' => 'custom',
                    'expires_at' => $expiresAt,
                    'status' => 'active',
                ]);

                $this->log('success', 'ssl', $certData['domain'], 'SSL certificate migrated successfully');
                $this->migratedData['ssl_certificates'][] = $certData['domain'];
            } catch (Exception $e) {
                $this->log('error', 'ssl', $certData['domain'], 'Failed to migrate SSL certificate: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate cron jobs
     */
    protected function migrateCronJobs(Account $account, array $cronJobs): void
    {
        foreach ($cronJobs as $cronData) {
            try {
                CronJob::create([
                    'account_id' => $account->id,
                    'schedule' => $cronData['schedule'],
                    'command' => $cronData['command'],
                    'status' => 'active',
                ]);

                $this->log('success', 'cron', substr($cronData['command'], 0, 50), 'Cron job migrated successfully');
                $this->migratedData['cron_jobs'][] = $cronData['schedule'];
            } catch (Exception $e) {
                $this->log('error', 'cron', $cronData['command'], 'Failed to migrate cron job: ' . $e->getMessage());
            }
        }
    }

    /**
     * Migrate FTP accounts
     */
    protected function migrateFtpAccounts(Account $account, array $ftpAccounts): void
    {
        foreach ($ftpAccounts as $ftpData) {
            try {
                FtpAccount::create([
                    'account_id' => $account->id,
                    'username' => $ftpData['username'],
                    'password' => $ftpData['password'],
                    'home_directory' => $ftpData['home_directory'],
                    'quota' => -1,
                    'uid' => $ftpData['uid'],
                    'gid' => $ftpData['gid'],
                    'status' => 'active',
                ]);

                $this->log('success', 'ftp', $ftpData['username'], 'FTP account migrated successfully');
                $this->migratedData['ftp_accounts'][] = $ftpData['username'];
            } catch (Exception $e) {
                $this->log('error', 'ftp', $ftpData['username'], 'Failed to migrate FTP account: ' . $e->getMessage());
            }
        }
    }

    /**
     * Parse DNS zone file
     */
    protected function parseDnsZoneFile(string $content): array
    {
        $records = [];
        $soa = '';

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || strpos($line, ';') === 0) {
                continue;
            }

            // Parse SOA
            if (strpos($line, 'SOA') !== false) {
                preg_match('/\s+IN\s+SOA\s+(\S+)/', $line, $matches);
                $soa = $matches[1] ?? '';
            }

            // Parse records
            if (preg_match('/^(\S+)\s+(\d+)?\s*IN\s+(\S+)\s+(.+)$/', $line, $matches)) {
                $record = [
                    'name' => $matches[1],
                    'ttl' => $matches[2] ?: 3600,
                    'type' => $matches[3],
                    'value' => trim($matches[4]),
                ];

                // Handle MX priority
                if ($record['type'] === 'MX' && preg_match('/^(\d+)\s+(.+)$/', $record['value'], $mxMatches)) {
                    $record['priority'] = (int)$mxMatches[1];
                    $record['value'] = $mxMatches[2];
                }

                $records[] = $record;
            }
        }

        return [
            'soa' => $soa,
            'records' => $records,
        ];
    }

    /**
     * Parse certificate expiration date
     */
    protected function parseCertificateExpiry(string $certificate): string
    {
        $cert = openssl_x509_parse($certificate);
        if ($cert && isset($cert['validTo_time_t'])) {
            return date('Y-m-d H:i:s', $cert['validTo_time_t']);
        }

        return now()->addYear()->format('Y-m-d H:i:s');
    }

    /**
     * Validate parsed data
     */
    protected function validateParsedData(array $data): array
    {
        $errors = [];

        if (empty($data['account']['username'])) {
            $errors[] = 'Account username is missing';
        }

        if (empty($data['account']['domain'])) {
            $errors[] = 'Account domain is missing';
        }

        return $errors;
    }

    /**
     * Build migration summary
     */
    protected function buildMigrationSummary(array $parsedData): array
    {
        return [
            'account' => $parsedData['account']['username'] ?? 'unknown',
            'domain' => $parsedData['account']['domain'] ?? 'unknown',
            'addon_domains' => count($parsedData['addon_domains'] ?? []),
            'subdomains' => count($parsedData['subdomains'] ?? []),
            'parked_domains' => count($parsedData['parked_domains'] ?? []),
            'email_accounts' => count($parsedData['email_accounts'] ?? []),
            'email_forwarders' => count($parsedData['email_forwarders'] ?? []),
            'databases' => count($parsedData['databases'] ?? []),
            'database_users' => count($parsedData['database_users'] ?? []),
            'dns_zones' => count($parsedData['dns_zones'] ?? []),
            'ssl_certificates' => count($parsedData['ssl_certificates'] ?? []),
            'cron_jobs' => count($parsedData['cron_jobs'] ?? []),
            'ftp_accounts' => count($parsedData['ftp_accounts'] ?? []),
        ];
    }

    /**
     * Build migration results
     */
    protected function buildMigrationResults(): array
    {
        return [
            'account' => isset($this->migratedData['account']) ? 1 : 0,
            'addon_domains' => count($this->migratedData['addon_domains'] ?? []),
            'subdomains' => count($this->migratedData['subdomains'] ?? []),
            'parked_domains' => count($this->migratedData['parked_domains'] ?? []),
            'email_accounts' => count($this->migratedData['email_accounts'] ?? []),
            'email_forwarders' => count($this->migratedData['email_forwarders'] ?? []),
            'databases' => count($this->migratedData['databases'] ?? []),
            'database_users' => count($this->migratedData['database_users'] ?? []),
            'dns_zones' => count($this->migratedData['dns_zones'] ?? []),
            'ssl_certificates' => count($this->migratedData['ssl_certificates'] ?? []),
            'cron_jobs' => count($this->migratedData['cron_jobs'] ?? []),
            'ftp_accounts' => count($this->migratedData['ftp_accounts'] ?? []),
        ];
    }

    /**
     * Update migration status
     */
    protected function updateStatus(string $status, int $progress, string $step): void
    {
        $this->migration->status = $status;
        $this->migration->progress_percentage = $progress;
        $this->migration->current_step = $step;

        if ($status === 'processing' && !$this->migration->started_at) {
            $this->migration->started_at = now();
        }

        $this->migration->save();
    }

    /**
     * Log migration activity
     */
    protected function log(string $level, ?string $category, ?string $itemName, string $message, array $details = []): void
    {
        MigrationLog::create([
            'migration_id' => $this->migration->id,
            'level' => $level,
            'category' => $category,
            'item_name' => $itemName,
            'message' => $message,
            'details' => !empty($details) ? $details : null,
        ]);
    }
}
