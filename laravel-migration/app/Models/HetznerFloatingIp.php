<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HetznerFloatingIp extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_hetzner_floating_ips';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'server_id',
        'hetzner_floating_ip_id',
        'name',
        'description',
        'ip_address',
        'type',
        'location',
        'is_assigned',
        'dns_ptr',
        'monthly_price',
        'labels',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_assigned' => 'boolean',
        'monthly_price' => 'decimal:2',
        'labels' => 'array',
    ];

    /**
     * Get the server this floating IP is assigned to
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(HetznerServer::class, 'server_id');
    }

    /**
     * Check if IP is assigned to a server
     */
    public function isAssigned(): bool
    {
        return $this->is_assigned && $this->server_id !== null;
    }

    /**
     * Check if IP is IPv4
     */
    public function isIpv4(): bool
    {
        return $this->type === 'ipv4';
    }

    /**
     * Check if IP is IPv6
     */
    public function isIpv6(): bool
    {
        return $this->type === 'ipv6';
    }

    /**
     * Scope: Assigned IPs
     */
    public function scopeAssigned($query)
    {
        return $query->where('is_assigned', true)->whereNotNull('server_id');
    }

    /**
     * Scope: Unassigned IPs
     */
    public function scopeUnassigned($query)
    {
        return $query->where('is_assigned', false)->whereNull('server_id');
    }
}
