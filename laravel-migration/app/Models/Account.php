<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'vp_accounts';

    protected $fillable = [
        'user_id',
        'package_id',
        'reseller_id',
        'username',
        'domain',
        'primary_ip',
        'status',
        'disk_used',
        'bandwidth_used',
        'suspended_at',
        'suspension_reason',
    ];

    protected $casts = [
        'suspended_at' => 'datetime',
    ];

    /**
     * Get the user that owns the account
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the reseller
     */
    public function reseller()
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    /**
     * Get addon domains
     */
    public function addonDomains()
    {
        return $this->hasMany(AddonDomain::class);
    }

    /**
     * Get subdomains
     */
    public function subdomains()
    {
        return $this->hasMany(Subdomain::class);
    }

    /**
     * Get parked domains
     */
    public function parkedDomains()
    {
        return $this->hasMany(ParkedDomain::class);
    }

    /**
     * Get email accounts
     */
    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class);
    }

    /**
     * Get databases
     */
    public function databases()
    {
        return $this->hasMany(Database::class);
    }

    /**
     * Get FTP accounts
     */
    public function ftpAccounts()
    {
        return $this->hasMany(FTPAccount::class);
    }

    /**
     * Get DNS zones
     */
    public function dnsZones()
    {
        return $this->hasMany(DNSZone::class);
    }

    /**
     * Get SSL certificates
     */
    public function sslCertificates()
    {
        return $this->hasMany(SSLCertificate::class);
    }

    /**
     * Get cron jobs
     */
    public function cronJobs()
    {
        return $this->hasMany(CronJob::class);
    }

    /**
     * Get backups
     */
    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Get installed applications
     */
    public function installedApps()
    {
        return $this->hasMany(InstalledApp::class);
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check disk usage percentage
     */
    public function diskUsagePercentage(): float
    {
        $quota = $this->package->disk_quota;
        if ($quota == -1) return 0; // Unlimited

        return ($this->disk_used / $quota) * 100;
    }

    /**
     * Check bandwidth usage percentage
     */
    public function bandwidthUsagePercentage(): float
    {
        $quota = $this->package->bandwidth_quota;
        if ($quota == -1) return 0; // Unlimited

        return ($this->bandwidth_used / $quota) * 100;
    }
}
