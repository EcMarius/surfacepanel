<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OVHServer extends Model
{
    use HasFactory;

    protected $table = 'vp_ovh_servers';

    protected $fillable = [
        'account_id',
        'service_name',
        'server_type',
        'display_name',
        'ip_address',
        'additional_ips',
        'datacenter',
        'os',
        'status',
        'cpu_cores',
        'ram_mb',
        'disk_gb',
        'bandwidth_mbps',
        'vrack_info',
        'notes',
        'provisioned_at',
    ];

    protected $casts = [
        'additional_ips' => 'array',
        'vrack_info' => 'array',
        'provisioned_at' => 'datetime',
    ];

    /**
     * Get the account that owns this server
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get billing records for this server
     */
    public function billingRecords()
    {
        return $this->hasMany(OVHBilling::class, 'server_id');
    }

    /**
     * Get failover IPs for this server
     */
    public function failoverIps()
    {
        return $this->hasMany(OVHFailoverIp::class, 'server_id');
    }

    /**
     * Check if server is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if server is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Get total monthly cost
     */
    public function getMonthlyMost()
    {
        return $this->billingRecords()
            ->where('billing_type', 'monthly')
            ->where('status', 'paid')
            ->whereMonth('billing_date', now()->month)
            ->whereYear('billing_date', now()->year)
            ->sum('amount');
    }

    /**
     * Get RAM in GB
     */
    public function getRamGb(): float
    {
        return $this->ram_mb ? round($this->ram_mb / 1024, 2) : 0;
    }

    /**
     * Format server specifications
     */
    public function getSpecifications(): string
    {
        $specs = [];

        if ($this->cpu_cores) {
            $specs[] = "{$this->cpu_cores} vCPU";
        }

        if ($this->ram_mb) {
            $specs[] = $this->getRamGb() . " GB RAM";
        }

        if ($this->disk_gb) {
            $specs[] = "{$this->disk_gb} GB Disk";
        }

        if ($this->bandwidth_mbps) {
            $specs[] = "{$this->bandwidth_mbps} Mbps";
        }

        return implode(' | ', $specs);
    }

    /**
     * Scope for filtering by server type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('server_type', $type);
    }

    /**
     * Scope for active servers only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
