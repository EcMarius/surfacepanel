<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GPUAllocation extends Model
{
    use HasFactory;

    protected $table = 'vp_gpu_allocations';

    protected $fillable = [
        'account_id',
        'gpu_device_id',
        'memory_allocated',
        'compute_percentage',
        'allocation_mode',
        'mig_profile',
        'status',
        'allocated_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
        'expires_at' => 'datetime',
        'compute_percentage' => 'decimal:2',
    ];

    /**
     * Get the account that owns this allocation
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the GPU device
     */
    public function gpuDevice()
    {
        return $this->belongsTo(GPUDevice::class, 'gpu_device_id');
    }

    /**
     * Get usage records for this allocation
     */
    public function usageRecords()
    {
        return $this->hasMany(GPUUsage::class, 'allocation_id');
    }

    /**
     * Get containers using this allocation
     */
    public function containers()
    {
        return $this->hasMany(GPUContainer::class, 'allocation_id');
    }

    /**
     * Check if allocation is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if allocation has expired
     */
    public function hasExpired(): bool
    {
        if (!$this->expires_at) return false;
        return now()->isAfter($this->expires_at);
    }

    /**
     * Get days until expiration
     */
    public function daysUntilExpiration(): ?int
    {
        if (!$this->expires_at) return null;
        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Check if allocation is exclusive mode
     */
    public function isExclusive(): bool
    {
        return $this->allocation_mode === 'exclusive';
    }

    /**
     * Check if allocation is shared mode
     */
    public function isShared(): bool
    {
        return $this->allocation_mode === 'shared';
    }

    /**
     * Check if allocation is MIG mode
     */
    public function isMIG(): bool
    {
        return $this->allocation_mode === 'mig';
    }
}
