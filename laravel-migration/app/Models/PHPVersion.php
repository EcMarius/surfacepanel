<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PHPVersion extends Model
{
    use HasFactory;

    protected $table = 'vp_php_versions';

    protected $fillable = [
        'version',
        'binary_path',
        'fpm_pool_dir',
        'php_ini_path',
        'is_default',
        'is_active',
        'extensions',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'extensions' => 'array',
    ];

    /**
     * Get domain PHP settings using this version
     */
    public function domainSettings()
    {
        return $this->hasMany(DomainPHPSetting::class);
    }

    /**
     * Check if version supports a specific extension
     */
    public function hasExtension(string $extension): bool
    {
        return in_array($extension, $this->extensions ?? []);
    }

    /**
     * Get formatted version (e.g., "PHP 8.2")
     */
    public function getFormattedVersionAttribute(): string
    {
        return "PHP {$this->version}";
    }

    /**
     * Get major.minor version (e.g., "8.2" from "8.2.15")
     */
    public function getMajorMinorVersion(): string
    {
        $parts = explode('.', $this->version);
        return "{$parts[0]}.{$parts[1]}";
    }

    /**
     * Detect installed PHP versions on the system
     */
    public static function detectSystemVersions(): array
    {
        $versions = [];
        $commonPaths = [
            '/usr/bin/php',
            '/usr/bin/php5.6',
            '/usr/bin/php7.0',
            '/usr/bin/php7.1',
            '/usr/bin/php7.2',
            '/usr/bin/php7.3',
            '/usr/bin/php7.4',
            '/usr/bin/php8.0',
            '/usr/bin/php8.1',
            '/usr/bin/php8.2',
            '/usr/bin/php8.3',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                exec("$path --version 2>&1", $output);
                if (preg_match('/PHP (\d+\.\d+\.\d+)/', $output[0] ?? '', $matches)) {
                    $fullVersion = $matches[1];
                    $parts = explode('.', $fullVersion);
                    $majorMinor = "{$parts[0]}.{$parts[1]}";

                    $versions[] = [
                        'version' => $majorMinor,
                        'full_version' => $fullVersion,
                        'binary_path' => $path,
                        'fpm_pool_dir' => "/etc/php/{$majorMinor}/fpm/pool.d",
                        'php_ini_path' => "/etc/php/{$majorMinor}/fpm/php.ini",
                    ];
                }
            }
        }

        return $versions;
    }
}
