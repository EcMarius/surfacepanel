<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainPHPSetting extends Model
{
    use HasFactory;

    protected $table = 'vp_domain_php_settings';

    protected $fillable = [
        'account_id',
        'domain',
        'php_version_id',
        'fpm_pool_name',
        'fpm_max_children',
        'fpm_start_servers',
        'fpm_min_spare_servers',
        'fpm_max_spare_servers',
    ];

    protected $casts = [
        'fpm_max_children' => 'integer',
        'fpm_start_servers' => 'integer',
        'fpm_min_spare_servers' => 'integer',
        'fpm_max_spare_servers' => 'integer',
    ];

    /**
     * Get the account that owns this setting
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the PHP version
     */
    public function phpVersion()
    {
        return $this->belongsTo(PHPVersion::class);
    }

    /**
     * Get PHP.ini overrides
     */
    public function iniOverrides()
    {
        return $this->hasMany(PHPIniOverride::class);
    }

    /**
     * Generate FPM pool name
     */
    public function generatePoolName(): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9-]/', '_', $this->domain);
        return "virpanel_{$this->account_id}_{$sanitized}";
    }

    /**
     * Get FPM pool configuration content
     */
    public function generateFPMPoolConfig(): string
    {
        $poolName = $this->fpm_pool_name ?: $this->generatePoolName();
        $account = $this->account;
        $username = $account->username;
        $socketPath = "/var/run/php/php{$this->phpVersion->version}-fpm-{$poolName}.sock";

        $config = "[{$poolName}]\n";
        $config .= "user = {$username}\n";
        $config .= "group = {$username}\n";
        $config .= "listen = {$socketPath}\n";
        $config .= "listen.owner = www-data\n";
        $config .= "listen.group = www-data\n";
        $config .= "listen.mode = 0660\n\n";

        $config .= "pm = dynamic\n";
        $config .= "pm.max_children = {$this->fpm_max_children}\n";
        $config .= "pm.start_servers = {$this->fpm_start_servers}\n";
        $config .= "pm.min_spare_servers = {$this->fpm_min_spare_servers}\n";
        $config .= "pm.max_spare_servers = {$this->fpm_max_spare_servers}\n\n";

        $config .= "chdir = /\n";
        $config .= "catch_workers_output = yes\n";
        $config .= "security.limit_extensions = .php .php3 .php4 .php5 .php7\n\n";

        // Add PHP.ini overrides
        if ($this->iniOverrides->isNotEmpty()) {
            foreach ($this->iniOverrides as $override) {
                $config .= "php_admin_value[{$override->directive}] = {$override->value}\n";
            }
        }

        return $config;
    }

    /**
     * Get socket path for Nginx configuration
     */
    public function getSocketPath(): string
    {
        $poolName = $this->fpm_pool_name ?: $this->generatePoolName();
        return "unix:/var/run/php/php{$this->phpVersion->version}-fpm-{$poolName}.sock";
    }
}
