<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HetznerVolume extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_hetzner_volumes';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'server_id',
        'hetzner_volume_id',
        'name',
        'size',
        'location',
        'format',
        'linux_device',
        'mount_point',
        'status',
        'auto_mount',
        'monthly_price',
        'labels',
        'attached_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'size' => 'integer',
        'auto_mount' => 'boolean',
        'monthly_price' => 'decimal:2',
        'labels' => 'array',
        'attached_at' => 'datetime',
    ];

    /**
     * Get the server this volume is attached to
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(HetznerServer::class, 'server_id');
    }

    /**
     * Check if volume is attached
     */
    public function isAttached(): bool
    {
        return $this->status === 'attached' && $this->server_id !== null;
    }

    /**
     * Check if volume is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if volume can be attached
     */
    public function canAttach(): bool
    {
        return in_array($this->status, ['available']);
    }

    /**
     * Check if volume can be detached
     */
    public function canDetach(): bool
    {
        return $this->status === 'attached';
    }

    /**
     * Scope: Attached volumes
     */
    public function scopeAttached($query)
    {
        return $query->where('status', 'attached')->whereNotNull('server_id');
    }

    /**
     * Scope: Available volumes
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Get formatted size
     */
    public function getFormattedSizeAttribute(): string
    {
        return $this->size . ' GB';
    }
}
