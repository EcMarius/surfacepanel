<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HetznerConfig extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'vp_hetzner_config';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'api_token',
        'is_enabled',
        'default_datacenter',
        'default_server_type',
        'default_image',
        'ssh_key_ids',
        'auto_backups',
        'monthly_budget_alert',
        'notification_email',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_backups' => 'boolean',
        'ssh_key_ids' => 'array',
        'monthly_budget_alert' => 'decimal:2',
    ];

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [
        'api_token',
    ];

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        $config = self::first();

        if (!$config) {
            $config = self::create([
                'is_enabled' => false,
                'default_datacenter' => 'nbg1',
                'default_server_type' => 'cx11',
                'default_image' => 'ubuntu-22.04',
            ]);
        }

        return $config;
    }

    /**
     * Check if Hetzner is enabled
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled && !empty($this->api_token);
    }

    /**
     * Get decrypted API token
     */
    public function getDecryptedTokenAttribute(): ?string
    {
        if (!$this->api_token) {
            return null;
        }

        try {
            return decrypt($this->api_token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted API token
     */
    public function setApiTokenAttribute($value): void
    {
        if ($value) {
            $this->attributes['api_token'] = encrypt($value);
        } else {
            $this->attributes['api_token'] = null;
        }
    }
}
