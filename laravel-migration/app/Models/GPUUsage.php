<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GPUUsage extends Model
{
    use HasFactory;

    protected $table = 'vp_gpu_usage';

    protected $fillable = [
        'allocation_id',
        'account_id',
        'memory_used',
        'utilization',
        'temperature',
        'power_draw',
        'processes_count',
        'processes_info',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'processes_info' => 'array',
    ];

    /**
     * Get the allocation
     */
    public function allocation()
    {
        return $this->belongsTo(GPUAllocation::class, 'allocation_id');
    }

    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get memory usage percentage
     */
    public function memoryUsagePercentage(): float
    {
        $allocation = $this->allocation;
        if (!$allocation || $allocation->memory_allocated == 0) return 0;

        return ($this->memory_used / $allocation->memory_allocated) * 100;
    }

    /**
     * Check if GPU is being heavily utilized
     */
    public function isHeavyUsage(): bool
    {
        return $this->utilization > 80;
    }

    /**
     * Get utilization level
     */
    public function utilizationLevel(): string
    {
        if ($this->utilization < 25) return 'low';
        if ($this->utilization < 50) return 'moderate';
        if ($this->utilization < 75) return 'high';
        return 'very_high';
    }
}
