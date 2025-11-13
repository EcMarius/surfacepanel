<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model
{
    use HasFactory;

    protected $table = 'vp_backup_schedules';

    protected $fillable = [
        'account_id',
        'name',
        'frequency',
        'cron_expression',
        'backup_type',
        'backup_time',
        'include_files',
        'include_databases',
        'include_emails',
        'is_encrypted',
        'encryption_id',
        'destination_ids',
        'rotation_id',
        'is_active',
        'is_system_wide',
        'last_run_at',
        'next_run_at',
        'successful_runs',
        'failed_runs',
    ];

    protected $casts = [
        'backup_time' => 'datetime',
        'include_files' => 'boolean',
        'include_databases' => 'boolean',
        'include_emails' => 'boolean',
        'is_encrypted' => 'boolean',
        'destination_ids' => 'array',
        'is_active' => 'boolean',
        'is_system_wide' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Get the account that owns the schedule
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the encryption configuration
     */
    public function encryption()
    {
        return $this->belongsTo(BackupEncryption::class, 'encryption_id');
    }

    /**
     * Get the rotation policy
     */
    public function rotation()
    {
        return $this->belongsTo(BackupRotation::class, 'rotation_id');
    }

    /**
     * Get backups created by this schedule
     */
    public function backups()
    {
        return $this->hasMany(Backup::class, 'schedule_id');
    }

    /**
     * Get destinations for this schedule
     */
    public function destinations()
    {
        if (!$this->destination_ids) {
            return collect();
        }

        return BackupDestination::whereIn('id', $this->destination_ids)->get();
    }

    /**
     * Check if schedule should run now
     */
    public function shouldRun(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->next_run_at) {
            return true;
        }

        return now()->gte($this->next_run_at);
    }

    /**
     * Calculate next run time
     */
    public function calculateNextRun(): void
    {
        $now = now();

        switch ($this->frequency) {
            case 'daily':
                $this->next_run_at = $now->addDay()->setTimeFromTimeString($this->backup_time);
                break;

            case 'weekly':
                $this->next_run_at = $now->addWeek()->setTimeFromTimeString($this->backup_time);
                break;

            case 'monthly':
                $this->next_run_at = $now->addMonth()->setTimeFromTimeString($this->backup_time);
                break;

            case 'custom':
                if ($this->cron_expression) {
                    // Parse cron expression and calculate next run
                    // This would need a cron expression parser library
                    $this->next_run_at = $now->addDay();
                }
                break;
        }

        $this->save();
    }

    /**
     * Mark as run
     */
    public function markAsRun(bool $success = true): void
    {
        $this->last_run_at = now();

        if ($success) {
            $this->successful_runs++;
        } else {
            $this->failed_runs++;
        }

        $this->calculateNextRun();
        $this->save();
    }
}
