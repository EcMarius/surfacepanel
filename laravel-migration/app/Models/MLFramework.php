<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MLFramework extends Model
{
    use HasFactory;

    protected $table = 'vp_ml_frameworks';

    protected $fillable = [
        'name',
        'version',
        'cuda_version',
        'python_version',
        'description',
        'docker_image',
        'dependencies',
        'status',
        'is_default',
        'installed_at',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'is_default' => 'boolean',
        'installed_at' => 'datetime',
    ];

    /**
     * Get containers using this framework
     */
    public function containers()
    {
        return $this->hasMany(GPUContainer::class, 'framework_id');
    }

    /**
     * Check if framework is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if framework is being installed
     */
    public function isInstalling(): bool
    {
        return $this->status === 'installing';
    }

    /**
     * Check if framework installation failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get full framework display name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->version}";
    }

    /**
     * Check if framework is deprecated
     */
    public function isDeprecated(): bool
    {
        return $this->status === 'deprecated';
    }
}
