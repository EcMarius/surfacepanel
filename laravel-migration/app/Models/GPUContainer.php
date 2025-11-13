<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GPUContainer extends Model
{
    use HasFactory;

    protected $table = 'vp_gpu_containers';

    protected $fillable = [
        'account_id',
        'allocation_id',
        'framework_id',
        'container_name',
        'container_id',
        'image',
        'port',
        'token',
        'type',
        'status',
        'environment_vars',
        'volumes',
        'startup_command',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'environment_vars' => 'array',
        'volumes' => 'array',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the allocation
     */
    public function allocation()
    {
        return $this->belongsTo(GPUAllocation::class, 'allocation_id');
    }

    /**
     * Get the framework
     */
    public function framework()
    {
        return $this->belongsTo(MLFramework::class, 'framework_id');
    }

    /**
     * Check if container is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if container is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Get container uptime in hours
     */
    public function uptime(): ?float
    {
        if (!$this->isRunning() || !$this->started_at) return null;
        return now()->diffInHours($this->started_at);
    }

    /**
     * Get access URL for Jupyter/services
     */
    public function getAccessUrl(): ?string
    {
        if (!$this->port || !$this->isRunning()) return null;

        $host = config('app.url');
        return "{$host}:{$this->port}";
    }

    /**
     * Get Jupyter access URL with token
     */
    public function getJupyterUrl(): ?string
    {
        if (!$this->token || !in_array($this->type, ['jupyter', 'jupyterlab'])) {
            return null;
        }

        $baseUrl = $this->getAccessUrl();
        if (!$baseUrl) return null;

        return "{$baseUrl}/?token={$this->token}";
    }
}
