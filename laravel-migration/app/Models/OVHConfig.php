<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OVHConfig extends Model
{
    use HasFactory;

    protected $table = 'vp_ovh_config';

    protected $fillable = [
        'application_key',
        'application_secret',
        'consumer_key',
        'endpoint',
        'is_active',
        'last_sync_at',
        'webhook_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get the singleton instance
     */
    public static function getInstance()
    {
        return static::firstOrCreate([]);
    }

    /**
     * Check if OVH is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->application_key)
            && !empty($this->application_secret)
            && !empty($this->consumer_key);
    }

    /**
     * Check if OVH integration is active and configured
     */
    public function isReady(): bool
    {
        return $this->is_active && $this->isConfigured();
    }

    /**
     * Get OVH API endpoint URL
     */
    public function getEndpointUrl(): string
    {
        $endpoints = [
            'ovh-eu' => 'https://eu.api.ovh.com/1.0',
            'ovh-ca' => 'https://ca.api.ovh.com/1.0',
            'ovh-us' => 'https://api.ovhcloud.com/1.0',
            'kimsufi-eu' => 'https://eu.api.kimsufi.com/1.0',
            'kimsufi-ca' => 'https://ca.api.kimsufi.com/1.0',
            'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
            'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
        ];

        return $endpoints[$this->endpoint] ?? $endpoints['ovh-eu'];
    }

    /**
     * Update last sync timestamp
     */
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }
}
