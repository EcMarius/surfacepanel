<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModalExecutionLog extends Model
{
    use HasFactory;

    protected $table = 'vp_modal_execution_logs';

    protected $fillable = [
        'account_id',
        'function_id',
        'execution_id',
        'started_at',
        'completed_at',
        'duration_ms',
        'status',
        'input',
        'output',
        'error_message',
        'logs',
        'memory_used_mb',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_ms' => 'integer',
        'memory_used_mb' => 'integer',
    ];

    /**
     * Get the account that owns this execution log
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the function associated with this execution log
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ModalFunction::class, 'function_id');
    }

    /**
     * Scope for logs by account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope for logs by function
     */
    public function scopeForFunction($query, $functionId)
    {
        return $query->where('function_id', $functionId);
    }

    /**
     * Scope for successful executions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed executions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for running executions
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Get duration in seconds
     */
    public function getDurationSeconds(): float
    {
        return round($this->duration_ms / 1000, 2);
    }

    /**
     * Check if execution was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if execution failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if execution is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
