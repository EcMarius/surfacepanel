<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupRotation extends Model
{
    use HasFactory;

    protected $table = 'vp_backup_rotations';

    protected $fillable = [
        'account_id',
        'name',
        'keep_daily',
        'keep_weekly',
        'keep_monthly',
        'keep_yearly',
        'max_backups',
        'max_storage_mb',
        'delete_from_remote',
        'is_active',
        'is_system_wide',
    ];

    protected $casts = [
        'delete_from_remote' => 'boolean',
        'is_active' => 'boolean',
        'is_system_wide' => 'boolean',
    ];

    /**
     * Get the account that owns the rotation policy
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get schedules using this rotation policy
     */
    public function schedules()
    {
        return $this->hasMany(BackupSchedule::class, 'rotation_id');
    }

    /**
     * Calculate expiration date for a backup based on its type
     */
    public function calculateExpiration(\DateTime $backupDate, string $backupFrequency): ?\DateTime
    {
        $expiration = clone $backupDate;

        switch ($backupFrequency) {
            case 'daily':
                if ($this->keep_daily > 0) {
                    $expiration->modify("+{$this->keep_daily} days");
                    return $expiration;
                }
                break;

            case 'weekly':
                if ($this->keep_weekly > 0) {
                    $weeks = $this->keep_weekly;
                    $expiration->modify("+{$weeks} weeks");
                    return $expiration;
                }
                break;

            case 'monthly':
                if ($this->keep_monthly > 0) {
                    $months = $this->keep_monthly;
                    $expiration->modify("+{$months} months");
                    return $expiration;
                }
                break;

            case 'yearly':
                if ($this->keep_yearly > 0) {
                    $years = $this->keep_yearly;
                    $expiration->modify("+{$years} years");
                    return $expiration;
                }
                break;
        }

        return null; // Never expires
    }

    /**
     * Get backups that should be rotated (expired)
     */
    public function getExpiredBackups($accountId = null)
    {
        $query = Backup::where('expires_at', '<', now())
            ->where('status', 'completed');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->get();
    }

    /**
     * Check if storage limit is exceeded
     */
    public function isStorageLimitExceeded($accountId = null): bool
    {
        if (!$this->max_storage_mb) {
            return false;
        }

        $query = Backup::where('status', 'completed');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $totalSize = $query->sum('size') / (1024 * 1024); // Convert bytes to MB

        return $totalSize > $this->max_storage_mb;
    }

    /**
     * Check if backup count limit is exceeded
     */
    public function isBackupCountExceeded($accountId = null): bool
    {
        if (!$this->max_backups) {
            return false;
        }

        $query = Backup::where('status', 'completed');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $count = $query->count();

        return $count > $this->max_backups;
    }

    /**
     * Get oldest backups to remove when limits are exceeded
     */
    public function getBackupsToRotate($accountId = null, $count = 1)
    {
        $query = Backup::where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->limit($count);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->get();
    }
}
