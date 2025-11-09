<?php

namespace VirPanel\Database;

use Doctrine\DBAL\Connection;

/**
 * Database Seeder
 *
 * Seeds the database with initial data
 */
class Seeder
{
    /**
     * Database connection
     *
     * @var Connection
     */
    protected Connection $connection;

    /**
     * Table prefix
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Create a new seeder instance
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->prefix = config('database.prefix', 'vp_');
    }

    /**
     * Run the database seeds
     *
     * @return void
     */
    public function run(): void
    {
        $this->seedDefaultPackages();
        $this->seedRootUser();
        $this->seedSettings();
        $this->seedDefaultTemplate();

        logger('Database seeding completed');
    }

    /**
     * Seed default packages
     *
     * @return void
     */
    protected function seedDefaultPackages(): void
    {
        $packages = [
            [
                'name' => 'starter',
                'display_name' => 'Starter Package',
                'description' => 'Perfect for small websites and blogs',
                'disk_quota' => 5120, // 5GB
                'bandwidth_quota' => 51200, // 50GB
                'inodes_quota' => 50000,
                'max_addon_domains' => 5,
                'max_parked_domains' => 5,
                'max_subdomains' => 25,
                'max_email_accounts' => 50,
                'max_email_lists' => 5,
                'max_databases' => 5,
                'max_ftp_accounts' => 5,
                'shell_access' => false,
                'cgi_access' => true,
                'dedicated_ip' => false,
                'private_nameservers' => false,
                'ssl_support' => true,
                'wildcard_ssl' => false,
                'max_hourly_emails' => 100,
                'max_daily_emails' => 500,
                'backup_enabled' => true,
                'backup_retention_days' => 7,
                'autossl_enabled' => true,
                'php_versions' => json_encode(['8.1', '8.2']),
                'monthly_price' => 4.99,
                'is_active' => true,
            ],
            [
                'name' => 'professional',
                'display_name' => 'Professional Package',
                'description' => 'Great for growing businesses',
                'disk_quota' => 20480, // 20GB
                'bandwidth_quota' => 204800, // 200GB
                'inodes_quota' => 200000,
                'max_addon_domains' => 25,
                'max_parked_domains' => 25,
                'max_subdomains' => 100,
                'max_email_accounts' => 0, // unlimited
                'max_email_lists' => 25,
                'max_databases' => 25,
                'max_ftp_accounts' => 25,
                'shell_access' => true,
                'cgi_access' => true,
                'dedicated_ip' => false,
                'private_nameservers' => false,
                'ssl_support' => true,
                'wildcard_ssl' => true,
                'max_hourly_emails' => 500,
                'max_daily_emails' => 2000,
                'backup_enabled' => true,
                'backup_retention_days' => 14,
                'autossl_enabled' => true,
                'php_versions' => json_encode(['7.4', '8.0', '8.1', '8.2']),
                'monthly_price' => 14.99,
                'is_active' => true,
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise Package',
                'description' => 'Maximum resources and features',
                'disk_quota' => 0, // unlimited
                'bandwidth_quota' => 0, // unlimited
                'inodes_quota' => 0, // unlimited
                'max_addon_domains' => 0, // unlimited
                'max_parked_domains' => 0,
                'max_subdomains' => 0,
                'max_email_accounts' => 0,
                'max_email_lists' => 0,
                'max_databases' => 0,
                'max_ftp_accounts' => 0,
                'shell_access' => true,
                'cgi_access' => true,
                'dedicated_ip' => true,
                'private_nameservers' => true,
                'ssl_support' => true,
                'wildcard_ssl' => true,
                'max_hourly_emails' => 0, // unlimited
                'max_daily_emails' => 0,
                'backup_enabled' => true,
                'backup_retention_days' => 30,
                'autossl_enabled' => true,
                'php_versions' => json_encode(['7.4', '8.0', '8.1', '8.2', '8.3']),
                'monthly_price' => 49.99,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $package) {
            $this->connection->insert($this->prefix . 'packages', $package);
        }

        logger('Default packages seeded');
    }

    /**
     * Seed root user
     *
     * @return void
     */
    protected function seedRootUser(): void
    {
        $password = password_hash('virpanel', PASSWORD_ARGON2ID);

        $this->connection->insert($this->prefix . 'users', [
            'username' => 'root',
            'email' => 'root@localhost',
            'password' => $password,
            'full_name' => 'System Administrator',
            'role' => 'root',
            'is_active' => true,
            'two_factor_enabled' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        logger('Root user created (username: root, password: virpanel)');
        logger('IMPORTANT: Please change the root password after installation!');
    }

    /**
     * Seed default settings
     *
     * @return void
     */
    protected function seedSettings(): void
    {
        $settings = [
            // General settings
            ['key' => 'app.name', 'value' => 'VirPanel', 'type' => 'string', 'group' => 'general'],
            ['key' => 'app.url', 'value' => 'https://panel.example.com', 'type' => 'string', 'group' => 'general'],
            ['key' => 'app.timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'general'],
            ['key' => 'app.locale', 'value' => 'en_US', 'type' => 'string', 'group' => 'general'],

            // Email settings
            ['key' => 'mail.driver', 'value' => 'smtp', 'type' => 'string', 'group' => 'mail'],
            ['key' => 'mail.host', 'value' => 'localhost', 'type' => 'string', 'group' => 'mail'],
            ['key' => 'mail.port', 'value' => '587', 'type' => 'int', 'group' => 'mail'],
            ['key' => 'mail.from.address', 'value' => 'noreply@example.com', 'type' => 'string', 'group' => 'mail'],
            ['key' => 'mail.from.name', 'value' => 'VirPanel', 'type' => 'string', 'group' => 'mail'],

            // Security settings
            ['key' => 'security.session_lifetime', 'value' => '120', 'type' => 'int', 'group' => 'security'],
            ['key' => 'security.password_min_length', 'value' => '12', 'type' => 'int', 'group' => 'security'],
            ['key' => 'security.max_login_attempts', 'value' => '5', 'type' => 'int', 'group' => 'security'],
            ['key' => 'security.lockout_duration', 'value' => '15', 'type' => 'int', 'group' => 'security'],

            // Backup settings
            ['key' => 'backup.enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'backup'],
            ['key' => 'backup.retention_days', 'value' => '7', 'type' => 'int', 'group' => 'backup'],
            ['key' => 'backup.path', 'value' => '/var/virpanel/backups', 'type' => 'string', 'group' => 'backup'],

            // Nameserver settings
            ['key' => 'nameserver.ns1', 'value' => 'ns1.example.com', 'type' => 'string', 'group' => 'nameserver'],
            ['key' => 'nameserver.ns2', 'value' => 'ns2.example.com', 'type' => 'string', 'group' => 'nameserver'],

            // AutoSSL settings
            ['key' => 'autossl.enabled', 'value' => 'true', 'type' => 'bool', 'group' => 'autossl'],
            ['key' => 'autossl.provider', 'value' => 'letsencrypt', 'type' => 'string', 'group' => 'autossl'],

            // License settings
            ['key' => 'license.key', 'value' => '', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license.server_url', 'value' => 'https://license.virpanel.com/api/v1', 'type' => 'string', 'group' => 'license'],
        ];

        foreach ($settings as $setting) {
            $this->connection->insert($this->prefix . 'settings', $setting);
        }

        logger('Default settings seeded');
    }

    /**
     * Seed default template
     *
     * @return void
     */
    protected function seedDefaultTemplate(): void
    {
        $templates = [
            [
                'name' => 'default',
                'display_name' => 'Default Theme',
                'description' => 'Clean and modern default theme',
                'version' => '1.0.0',
                'author' => 'VirPanel Team',
                'type' => 'both',
                'path' => '/usr/local/virpanel/public/themes/default',
                'is_active' => true,
                'is_default' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($templates as $template) {
            $this->connection->insert($this->prefix . 'templates', $template);
        }

        logger('Default template seeded');
    }

    /**
     * Clear all seeded data
     *
     * @return void
     */
    public function clear(): void
    {
        $tables = [
            'templates',
            'settings',
            'users',
            'packages',
        ];

        foreach ($tables as $table) {
            $this->connection->executeStatement("DELETE FROM {$this->prefix}{$table}");
        }

        logger('Seeded data cleared');
    }
}
