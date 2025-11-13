<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HetznerSnapshot extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_hetzner_snapshots';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'server_id',
        'hetzner_snapshot_id',
        'type',
        'source_id',
        'name',
        'description',
        'size',
        'status',
        'monthly_price',
        'labels',
        'created_at_hetzner',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'size' => 'integer',
        'monthly_price' => 'decimal:2',
        'labels' => 'array',
        'created_at_hetzner' => 'datetime',
    ];

    /**
     * Get the server this snapshot belongs to
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(HetznerServer::class, 'server_id');
    }

    /**
     * Check if snapshot is available
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if snapshot is a server snapshot
     */
    public function isServerSnapshot(): bool
    {
        return $this->type === 'server';
    }

    /**
     * Check if snapshot is a volume snapshot
     */
    public function isVolumeSnapshot(): bool
    {
        return $this->type === 'volume';
    }

    /**
     * Check if snapshot can be restored
     */
    public function canRestore(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Scope: Server snapshots
     */
    public function scopeServerSnapshots($query)
    {
        return $query->where('type', 'server');
    }

    /**
     * Scope: Volume snapshots
     */
    public function scopeVolumeSnapshots($query)
    {
        return $query->where('type', 'volume');
    }

    /**
     * Scope: Available snapshots
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
