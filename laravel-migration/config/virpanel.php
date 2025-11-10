<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VirPanel Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the VirPanel hosting control panel
    |
    */

    'name' => env('APP_NAME', 'VirPanel'),

    'hostname' => env('VIRPANEL_HOSTNAME', gethostname()),

    'primary_ip' => env('VIRPANEL_PRIMARY_IP', '127.0.0.1'),

    /*
    |--------------------------------------------------------------------------
    | Port Configuration
    |--------------------------------------------------------------------------
    |
    | WHM Admin Panel runs on port 15443
    | User Panel runs on port 15444
    |
    */

    'whm_port' => env('VIRPANEL_WHM_PORT', 15443),

    'user_port' => env('VIRPANEL_USER_PORT', 15444),

    /*
    |--------------------------------------------------------------------------
    | Active Panel
    |--------------------------------------------------------------------------
    |
    | This is automatically set by middleware based on the port
    | Values: 'admin' or 'user'
    |
    */

    'active_panel' => 'user',

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    */

    'database_prefix' => env('DB_PREFIX', 'vp_'),

    /*
    |--------------------------------------------------------------------------
    | Default Package Limits
    |--------------------------------------------------------------------------
    */

    'default_limits' => [
        'disk_quota' => 1024, // MB
        'bandwidth_quota' => 10240, // MB
        'email_accounts' => 10,
        'databases' => 5,
        'ftp_accounts' => 5,
        'addon_domains' => 1,
        'subdomains' => 10,
        'parked_domains' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Manager Settings
    |--------------------------------------------------------------------------
    */

    'file_manager' => [
        'max_upload_size' => env('VIRPANEL_MAX_UPLOAD_SIZE', 100), // MB
        'allowed_extensions' => [
            'php', 'html', 'css', 'js', 'txt', 'jpg', 'jpeg', 'png', 'gif',
            'pdf', 'zip', 'tar', 'gz', 'sql', 'xml', 'json', 'md'
        ],
        'forbidden_files' => ['.htaccess', '.htpasswd', 'wp-config.php'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    */

    'backup' => [
        'storage_path' => env('VIRPANEL_BACKUP_PATH', '/var/virpanel/backups'),
        'max_backups_per_account' => 10,
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Settings
    |--------------------------------------------------------------------------
    */

    'ssl' => [
        'letsencrypt_enabled' => true,
        'letsencrypt_email' => env('VIRPANEL_SSL_EMAIL', env('ADMIN_EMAIL')),
        'auto_renew' => true,
        'renew_days_before' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Settings
    |--------------------------------------------------------------------------
    */

    'email' => [
        'default_quota' => 250, // MB
        'max_quota' => 1024, // MB
        'spam_assassin_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Settings
    |--------------------------------------------------------------------------
    */

    'dns' => [
        'default_ttl' => 3600,
        'nameservers' => [
            env('VIRPANEL_NS1', 'ns1.example.com'),
            env('VIRPANEL_NS2', 'ns2.example.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Installer
    |--------------------------------------------------------------------------
    */

    'app_installer' => [
        'enabled' => true,
        'nodejs_enabled' => true,
        'pm2_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'two_factor_enabled' => false,
        'password_min_length' => 12,
        'session_timeout' => 120, // minutes
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
    ],
];
