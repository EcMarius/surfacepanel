<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $table = 'vp_backups';

    protected $fillable = [
        'account_id',
        'schedule_id',
        'filename',
        'path',
        'type',
        'size',
        'status',
        'error_message',
        'is_encrypted',
        'encryption_method',
        'is_remote',
        'remote_locations',
        'checksum',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'is_remote' => 'boolean',
        'remote_locations' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the account that owns the backup
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the schedule that created this backup
     */
    public function schedule()
    {
        return $this->belongsTo(BackupSchedule::class, 'schedule_id');
    }

    /**
     * Check if backup is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if backup has failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if backup is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if backup is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->gte($this->expires_at);
    }

    /**
     * Mark backup as started
     */
    public function markAsStarted(): void
    {
        $this->status = 'processing';
        $this->started_at = now();
        $this->save();
    }

    /**
     * Mark backup as completed
     */
    public function markAsCompleted(int $size, string $checksum = null): void
    {
        $this->status = 'completed';
        $this->size = $size;
        $this->checksum = $checksum;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark backup as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Mark backup as uploaded to remote
     */
    public function markAsUploaded(array $destinationIds): void
    {
        $this->status = 'uploaded';
        $this->is_remote = true;
        $this->remote_locations = $destinationIds;
        $this->save();
    }

    /**
     * Mark backup as verified
     */
    public function markAsVerified(): void
    {
        $this->status = 'verified';
        $this->save();
    }

    /**
     * Get human-readable size
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get duration in minutes
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Verify backup integrity
     */
    public function verify(): bool
    {
        if (!file_exists($this->path)) {
            return false;
        }

        if (!$this->checksum) {
            return false;
        }

        $actualChecksum = hash_file('sha256', $this->path);
        return $actualChecksum === $this->checksum;
    }

    /**
     * Delete backup file
     */
    public function deleteFile(): bool
    {
        if (file_exists($this->path)) {
            return unlink($this->path);
        }

        return true;
    }
}
