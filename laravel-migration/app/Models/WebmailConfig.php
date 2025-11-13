<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebmailConfig extends Model
{
    use HasFactory;

    protected $table = 'vp_webmail_config';

    protected $fillable = [
        'is_installed',
        'version',
        'installation_path',
        'database_name',
        'database_user',
        'database_password',
        'des_key',
        'enabled_plugins',
        'default_theme',
        'imap_host',
        'smtp_host',
        'smtp_auth',
        'product_name',
        'force_https',
        'session_lifetime',
        'settings',
    ];

    protected $casts = [
        'is_installed' => 'boolean',
        'smtp_auth' => 'boolean',
        'force_https' => 'boolean',
        'session_lifetime' => 'integer',
        'enabled_plugins' => 'array',
        'settings' => 'array',
    ];

    /**
     * Get the singleton configuration instance
     */
    public static function getInstance(): self
    {
        $config = self::first();

        if (!$config) {
            $config = self::create([
                'is_installed' => false,
                'installation_path' => '/var/www/roundcube',
                'database_name' => 'roundcube',
                'default_theme' => 'elastic',
                'imap_host' => 'localhost:143',
                'smtp_host' => 'localhost:587',
                'smtp_auth' => true,
                'product_name' => 'VirPanel Webmail',
                'force_https' => true,
                'session_lifetime' => 30,
                'enabled_plugins' => ['archive', 'zipdownload', 'managesieve'],
            ]);
        }

        return $config;
    }

    /**
     * Check if Roundcube is installed
     */
    public function isInstalled(): bool
    {
        return $this->is_installed && file_exists($this->installation_path);
    }

    /**
     * Get webmail URL
     */
    public function getWebmailUrl(): string
    {
        $protocol = $this->force_https ? 'https' : 'http';
        $host = request()->getHost();

        return "{$protocol}://{$host}/webmail";
    }

    /**
     * Get available themes
     */
    public static function getAvailableThemes(): array
    {
        return [
            'elastic' => 'Elastic (Default)',
            'larry' => 'Larry (Classic)',
            'classic' => 'Classic',
        ];
    }

    /**
     * Get available plugins
     */
    public static function getAvailablePlugins(): array
    {
        return [
            'archive' => [
                'name' => 'Archive',
                'description' => 'Move messages to an archive folder',
            ],
            'zipdownload' => [
                'name' => 'Zip Download',
                'description' => 'Download multiple messages or folders as a zip archive',
            ],
            'managesieve' => [
                'name' => 'ManageSieve',
                'description' => 'Manage email filters using Sieve protocol',
            ],
            'password' => [
                'name' => 'Password',
                'description' => 'Allow users to change their password',
            ],
            'markasjunk' => [
                'name' => 'Mark as Junk',
                'description' => 'Mark messages as spam/junk',
            ],
            'emoticons' => [
                'name' => 'Emoticons',
                'description' => 'Insert emoticons in messages',
            ],
            'newmail_notifier' => [
                'name' => 'New Mail Notifier',
                'description' => 'Notify users about new mail',
            ],
        ];
    }

    /**
     * Generate encryption key for Roundcube
     */
    public static function generateDesKey(): string
    {
        return bin2hex(random_bytes(12)); // 24 character hex string
    }

    /**
     * Generate random database password
     */
    public static function generateDatabasePassword(): string
    {
        return bin2hex(random_bytes(16)); // 32 character hex string
    }
}
