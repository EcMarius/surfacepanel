<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OVHFailoverIp extends Model
{
    use HasFactory;

    protected $table = 'vp_ovh_failover_ips';

    protected $fillable = [
        'server_id',
        'ip_address',
        'netmask',
        'routed_to',
        'reverse_dns',
        'status',
    ];

    /**
     * Get the server this IP belongs to
     */
    public function server()
    {
        return $this->belongsTo(OVHServer::class, 'server_id');
    }

    /**
     * Check if IP is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if IP is parked
     */
    public function isParked(): bool
    {
        return $this->status === 'parked';
    }
}
