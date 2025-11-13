<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModalFunction extends Model
{
    use HasFactory;

    protected $table = 'vp_modal_functions';

    protected $fillable = [
        'account_id',
        'function_name',
        'function_id',
        'description',
        'runtime',
        'code',
        'entrypoint',
        'requirements',
        'environment_variables',
        'secrets',
        'gpu_enabled',
        'gpu_type',
        'gpu_count',
        'cpu_count',
        'memory_mb',
        'timeout',
        'endpoint_url',
        'status',
        'deployment_error',
        'last_deployed_at',
        'version',
    ];

    protected $casts = [
        'requirements' => 'array',
        'environment_variables' => 'array',
        'secrets' => 'array',
        'gpu_enabled' => 'boolean',
        'gpu_count' => 'integer',
        'cpu_count' => 'integer',
        'memory_mb' => 'integer',
        'timeout' => 'integer',
        'last_deployed_at' => 'datetime',
    ];

    protected $hidden = [
        'secrets',
    ];

    /**
     * Get the account that owns the function
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the jobs for this function
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ModalJob::class, 'function_id');
    }

    /**
     * Get the usage records for this function
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(ModalUsage::class, 'function_id');
    }

    /**
     * Get the versions for this function
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ModalFunctionVersion::class, 'function_id');
    }

    /**
     * Get the execution logs for this function
     */
    public function executionLogs(): HasMany
    {
        return $this->hasMany(ModalExecutionLog::class, 'function_id');
    }

    /**
     * Scope for active functions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for deployed functions
     */
    public function scopeDeployed($query)
    {
        return $query->whereNotNull('function_id')->whereIn('status', ['active', 'stopped']);
    }

    /**
     * Scope for GPU-enabled functions
     */
    public function scopeGpuEnabled($query)
    {
        return $query->where('gpu_enabled', true);
    }

    /**
     * Scope for functions by account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Check if function is deployed
     */
    public function isDeployed(): bool
    {
        return !empty($this->function_id) && in_array($this->status, ['active', 'stopped']);
    }

    /**
     * Check if function is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if function uses GPU
     */
    public function usesGPU(): bool
    {
        return $this->gpu_enabled && $this->gpu_count > 0;
    }

    /**
     * Get resource configuration as array
     */
    public function getResourceConfig(): array
    {
        return [
            'cpu_count' => $this->cpu_count,
            'memory_mb' => $this->memory_mb,
            'gpu_enabled' => $this->gpu_enabled,
            'gpu_type' => $this->gpu_type,
            'gpu_count' => $this->gpu_count,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * Get estimated hourly cost in USD
     */
    public function getEstimatedHourlyCost(): float
    {
        $cost = 0.0;

        // Base CPU/Memory cost (example pricing)
        $cost += ($this->cpu_count * 0.01); // $0.01 per CPU hour
        $cost += ($this->memory_mb / 1024 * 0.005); // $0.005 per GB hour

        // GPU cost (example pricing)
        if ($this->usesGPU()) {
            $gpuCost = match($this->gpu_type) {
                'T4' => 0.35,
                'A10G' => 0.60,
                'A100' => 1.10,
                'A100-80GB' => 1.60,
                'H100' => 2.00,
                default => 0.50,
            };
            $cost += $gpuCost * $this->gpu_count;
        }

        return round($cost, 4);
    }
}
