<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OVHCloudInstance extends Model
{
    use HasFactory;

    protected $table = 'vp_ovh_cloud_instances';

    protected $fillable = [
        'account_id',
        'instance_id',
        'project_id',
        'name',
        'flavor_id',
        'image_id',
        'region',
        'ip_address',
        'ip_addresses',
        'status',
        'vcpus',
        'ram_mb',
        'disk_gb',
        'monthly_billing',
        'hourly_rate',
        'monthly_rate',
    ];

    protected $casts = [
        'ip_addresses' => 'array',
        'monthly_billing' => 'boolean',
        'hourly_rate' => 'decimal:4',
        'monthly_rate' => 'decimal:2',
    ];

    /**
     * Get the account that owns this instance
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if instance is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if instance is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Get estimated monthly cost
     */
    public function getEstimatedMonthlyCost(): float
    {
        if ($this->monthly_billing && $this->monthly_rate) {
            return (float) $this->monthly_rate;
        }

        if ($this->hourly_rate) {
            return (float) $this->hourly_rate * 730; // Average hours per month
        }

        return 0;
    }

    /**
     * Scope for active instances
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
