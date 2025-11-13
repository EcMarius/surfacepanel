<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_migration_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'migration_id',
        'level',
        'category',
        'item_name',
        'message',
        'details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get the migration that owns the log
     */
    public function migration()
    {
        return $this->belongsTo(Migration::class);
    }

    /**
     * Check if log is an error
     */
    public function isError(): bool
    {
        return $this->level === 'error';
    }

    /**
     * Check if log is a warning
     */
    public function isWarning(): bool
    {
        return $this->level === 'warning';
    }

    /**
     * Check if log is info
     */
    public function isInfo(): bool
    {
        return $this->level === 'info';
    }

    /**
     * Check if log is success
     */
    public function isSuccess(): bool
    {
        return $this->level === 'success';
    }

    /**
     * Get log icon based on level
     */
    public function getIconAttribute(): string
    {
        return match($this->level) {
            'error' => 'fa-times-circle text-danger',
            'warning' => 'fa-exclamation-triangle text-warning',
            'success' => 'fa-check-circle text-success',
            'info' => 'fa-info-circle text-info',
            default => 'fa-circle',
        };
    }

    /**
     * Get log color class based on level
     */
    public function getColorClassAttribute(): string
    {
        return match($this->level) {
            'error' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            'info' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Scope a query to only include error logs
     */
    public function scopeErrors($query)
    {
        return $query->where('level', 'error');
    }

    /**
     * Scope a query to only include warning logs
     */
    public function scopeWarnings($query)
    {
        return $query->where('level', 'warning');
    }

    /**
     * Scope a query to only include success logs
     */
    public function scopeSuccess($query)
    {
        return $query->where('level', 'success');
    }

    /**
     * Scope a query to filter by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
