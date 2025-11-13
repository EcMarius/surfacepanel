<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HetznerServer extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_hetzner_servers';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'hetzner_server_id',
        'name',
        'server_type',
        'datacenter',
        'location',
        'image',
        'status',
        'public_ipv4',
        'public_ipv6',
        'private_networks',
        'disk_size',
        'vcpus',
        'memory',
        'hourly_price',
        'monthly_price',
        'backups_enabled',
        'labels',
        'root_password',
        'created_at_hetzner',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'private_networks' => 'array',
        'labels' => 'array',
        'hourly_price' => 'decimal:4',
        'monthly_price' => 'decimal:2',
        'backups_enabled' => 'boolean',
        'disk_size' => 'integer',
        'vcpus' => 'integer',
        'memory' => 'integer',
        'created_at_hetzner' => 'datetime',
    ];

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [
        'root_password',
    ];

    /**
     * Get the account that owns the server
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user that owns the server
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the volumes attached to this server
     */
    public function volumes(): HasMany
    {
        return $this->hasMany(HetznerVolume::class, 'server_id');
    }

    /**
     * Get the snapshots of this server
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(HetznerSnapshot::class, 'server_id');
    }

    /**
     * Get the floating IPs assigned to this server
     */
    public function floatingIps(): HasMany
    {
        return $this->hasMany(HetznerFloatingIp::class, 'server_id');
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if server is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Check if server can be started
     */
    public function canStart(): bool
    {
        return in_array($this->status, ['stopped']);
    }

    /**
     * Check if server can be stopped
     */
    public function canStop(): bool
    {
        return in_array($this->status, ['running']);
    }

    /**
     * Check if server can be rebooted
     */
    public function canReboot(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Get decrypted root password
     */
    public function getDecryptedPasswordAttribute(): ?string
    {
        if (!$this->root_password) {
            return null;
        }

        try {
            return decrypt($this->root_password);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted root password
     */
    public function setRootPasswordAttribute($value): void
    {
        if ($value) {
            $this->attributes['root_password'] = encrypt($value);
        } else {
            $this->attributes['root_password'] = null;
        }
    }

    /**
     * Scope: Active servers
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['deleted', 'deleting']);
    }

    /**
     * Scope: Running servers
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Get server type details
     */
    public function getServerTypeDetails(): array
    {
        $types = [
            'cx11' => ['vcpus' => 1, 'memory' => 2048, 'disk' => 20, 'name' => 'CX11'],
            'cx21' => ['vcpus' => 2, 'memory' => 4096, 'disk' => 40, 'name' => 'CX21'],
            'cx31' => ['vcpus' => 2, 'memory' => 8192, 'disk' => 80, 'name' => 'CX31'],
            'cx41' => ['vcpus' => 4, 'memory' => 16384, 'disk' => 160, 'name' => 'CX41'],
            'cx51' => ['vcpus' => 8, 'memory' => 32768, 'disk' => 240, 'name' => 'CX51'],
            'cpx11' => ['vcpus' => 2, 'memory' => 2048, 'disk' => 40, 'name' => 'CPX11'],
            'cpx21' => ['vcpus' => 3, 'memory' => 4096, 'disk' => 80, 'name' => 'CPX21'],
            'cpx31' => ['vcpus' => 4, 'memory' => 8192, 'disk' => 160, 'name' => 'CPX31'],
            'cpx41' => ['vcpus' => 8, 'memory' => 16384, 'disk' => 240, 'name' => 'CPX41'],
            'cpx51' => ['vcpus' => 16, 'memory' => 32768, 'disk' => 360, 'name' => 'CPX51'],
            'ccx12' => ['vcpus' => 2, 'memory' => 8192, 'disk' => 80, 'name' => 'CCX12'],
            'ccx22' => ['vcpus' => 4, 'memory' => 16384, 'disk' => 160, 'name' => 'CCX22'],
            'ccx32' => ['vcpus' => 8, 'memory' => 32768, 'disk' => 240, 'name' => 'CCX32'],
            'ccx42' => ['vcpus' => 16, 'memory' => 65536, 'disk' => 360, 'name' => 'CCX42'],
            'ccx52' => ['vcpus' => 32, 'memory' => 131072, 'disk' => 600, 'name' => 'CCX52'],
        ];

        return $types[$this->server_type] ?? [];
    }
}
