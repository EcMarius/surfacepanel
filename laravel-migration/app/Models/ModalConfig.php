<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModalConfig extends Model
{
    use HasFactory;

    protected $table = 'vp_modal_config';

    protected $fillable = [
        'api_key',
        'api_secret',
        'workspace_name',
        'workspace_id',
        'is_enabled',
        'default_settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'default_settings' => 'array',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    /**
     * Get the singleton configuration instance
     */
    public static function getInstance(): self
    {
        $config = self::first();

        if (!$config) {
            $config = self::create([
                'is_enabled' => false,
            ]);
        }

        return $config;
    }

    /**
     * Check if Modal.com is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->api_key) && !empty($this->api_secret);
    }

    /**
     * Check if Modal.com is enabled and configured
     */
    public function isActive(): bool
    {
        return $this->is_enabled && $this->isConfigured();
    }

    /**
     * Get available GPU types
     */
    public static function getAvailableGPUTypes(): array
    {
        return [
            'T4' => 'NVIDIA T4 (16GB) - Entry level',
            'A10G' => 'NVIDIA A10G (24GB) - Mid range',
            'A100' => 'NVIDIA A100 (40GB) - High performance',
            'A100-80GB' => 'NVIDIA A100 (80GB) - Maximum performance',
            'H100' => 'NVIDIA H100 (80GB) - Latest generation',
        ];
    }

    /**
     * Get available Python runtimes
     */
    public static function getAvailableRuntimes(): array
    {
        return [
            'python3.9' => 'Python 3.9',
            'python3.10' => 'Python 3.10',
            'python3.11' => 'Python 3.11 (Recommended)',
            'python3.12' => 'Python 3.12 (Latest)',
        ];
    }
}
