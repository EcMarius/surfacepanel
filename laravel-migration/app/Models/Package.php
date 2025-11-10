<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $table = 'vp_packages';

    protected $fillable = [
        'name',
        'description',
        'disk_quota',
        'bandwidth_quota',
        'email_accounts',
        'databases',
        'ftp_accounts',
        'addon_domains',
        'subdomains',
        'parked_domains',
        'monthly_price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
    ];

    /**
     * Get accounts using this package
     */
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Check if package is unlimited for a specific resource
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->{$resource} == -1;
    }

    /**
     * Get formatted disk quota
     */
    public function getFormattedDiskQuota(): string
    {
        return $this->disk_quota == -1 ? 'Unlimited' : $this->disk_quota . ' MB';
    }

    /**
     * Get formatted bandwidth quota
     */
    public function getFormattedBandwidthQuota(): string
    {
        return $this->bandwidth_quota == -1 ? 'Unlimited' : $this->bandwidth_quota . ' MB';
    }
}
