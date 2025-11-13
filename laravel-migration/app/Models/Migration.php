<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Migration extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_migrations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'backup_filename',
        'backup_path',
        'backup_size',
        'source_type',
        'status',
        'progress_percentage',
        'current_step',
        'migration_summary',
        'migration_results',
        'validation_errors',
        'error_message',
        'started_at',
        'completed_at',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'backup_size' => 'integer',
        'progress_percentage' => 'integer',
        'migration_summary' => 'array',
        'migration_results' => 'array',
        'validation_errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who created the migration
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the migration logs
     */
    public function logs()
    {
        return $this->hasMany(MigrationLog::class);
    }

    /**
     * Get error logs
     */
    public function errorLogs()
    {
        return $this->hasMany(MigrationLog::class)->where('level', 'error');
    }

    /**
     * Get warning logs
     */
    public function warningLogs()
    {
        return $this->hasMany(MigrationLog::class)->where('level', 'warning');
    }

    /**
     * Check if migration is complete
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if migration has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if migration is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['uploading', 'parsing', 'processing']);
    }

    /**
     * Check if migration is rolled back
     */
    public function isRolledBack(): bool
    {
        return $this->status === 'rolled_back';
    }

    /**
     * Get formatted backup size
     */
    public function getFormattedBackupSizeAttribute(): string
    {
        $bytes = $this->backup_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Get duration in human readable format
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $diff = $this->started_at->diff($end);

        if ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm ' . $diff->s . 's';
        } elseif ($diff->i > 0) {
            return $diff->i . 'm ' . $diff->s . 's';
        } else {
            return $diff->s . 's';
        }
    }

    /**
     * Scope a query to only include completed migrations
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed migrations
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include in-progress migrations
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['uploading', 'parsing', 'processing']);
    }
}
