<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GPUDevice extends Model
{
    use HasFactory;

    protected $table = 'vp_gpu_devices';

    protected $fillable = [
        'uuid',
        'name',
        'index',
        'memory_total',
        'memory_free',
        'memory_used',
        'driver_version',
        'cuda_version',
        'temperature',
        'power_draw',
        'power_limit',
        'utilization',
        'status',
        'pci_bus_id',
        'last_detected_at',
    ];

    protected $casts = [
        'last_detected_at' => 'datetime',
    ];

    /**
     * Get allocations for this GPU
     */
    public function allocations()
    {
        return $this->hasMany(GPUAllocation::class, 'gpu_device_id');
    }

    /**
     * Get active allocations
     */
    public function activeAllocations()
    {
        return $this->hasMany(GPUAllocation::class, 'gpu_device_id')->where('status', 'active');
    }

    /**
     * Check if GPU is available for allocation
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if GPU is allocated
     */
    public function isAllocated(): bool
    {
        return $this->status === 'allocated';
    }

    /**
     * Get memory usage percentage
     */
    public function memoryUsagePercentage(): float
    {
        if ($this->memory_total == 0) return 0;
        return ($this->memory_used / $this->memory_total) * 100;
    }

    /**
     * Get power usage percentage
     */
    public function powerUsagePercentage(): float
    {
        if ($this->power_limit == 0) return 0;
        return ($this->power_draw / $this->power_limit) * 100;
    }

    /**
     * Check if GPU is healthy (not overheating)
     */
    public function isHealthy(): bool
    {
        return $this->temperature < 80; // Threshold: 80°C
    }

    /**
     * Get temperature status
     */
    public function temperatureStatus(): string
    {
        if ($this->temperature < 60) return 'normal';
        if ($this->temperature < 75) return 'warm';
        if ($this->temperature < 85) return 'hot';
        return 'critical';
    }
}
