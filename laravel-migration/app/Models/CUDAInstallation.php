<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CUDAInstallation extends Model
{
    use HasFactory;

    protected $table = 'vp_cuda_installations';

    protected $fillable = [
        'version',
        'install_path',
        'driver_version',
        'has_cudnn',
        'cudnn_version',
        'has_nccl',
        'nccl_version',
        'has_tensorrt',
        'tensorrt_version',
        'is_default',
        'status',
        'installed_at',
    ];

    protected $casts = [
        'has_cudnn' => 'boolean',
        'has_nccl' => 'boolean',
        'has_tensorrt' => 'boolean',
        'is_default' => 'boolean',
        'installed_at' => 'datetime',
    ];

    /**
     * Check if CUDA is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if CUDA is being installed
     */
    public function isInstalling(): bool
    {
        return $this->status === 'installing';
    }

    /**
     * Check if CUDA installation failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get full CUDA display name
     */
    public function getFullNameAttribute(): string
    {
        return "CUDA {$this->version}";
    }

    /**
     * Get list of installed components
     */
    public function getComponentsAttribute(): array
    {
        $components = ['CUDA Toolkit'];

        if ($this->has_cudnn) {
            $components[] = "cuDNN {$this->cudnn_version}";
        }

        if ($this->has_nccl) {
            $components[] = "NCCL {$this->nccl_version}";
        }

        if ($this->has_tensorrt) {
            $components[] = "TensorRT {$this->tensorrt_version}";
        }

        return $components;
    }
}
