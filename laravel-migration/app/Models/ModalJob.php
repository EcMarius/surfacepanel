<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModalJob extends Model
{
    use HasFactory;

    protected $table = 'vp_modal_jobs';

    protected $fillable = [
        'account_id',
        'function_id',
        'job_name',
        'job_id',
        'description',
        'schedule',
        'parameters',
        'is_active',
        'last_run_at',
        'next_run_at',
        'last_status',
        'last_error',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    /**
     * Get the account that owns the job
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the function associated with this job
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ModalFunction::class, 'function_id');
    }

    /**
     * Scope for active jobs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for jobs by account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope for jobs by function
     */
    public function scopeForFunction($query, $functionId)
    {
        return $query->where('function_id', $functionId);
    }

    /**
     * Scope for jobs due to run
     */
    public function scopeDueToRun($query)
    {
        return $query->where('is_active', true)
                     ->where('next_run_at', '<=', now());
    }

    /**
     * Check if job is deployed to Modal
     */
    public function isDeployed(): bool
    {
        return !empty($this->job_id);
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRate(): float
    {
        $total = $this->success_count + $this->failure_count;

        if ($total === 0) {
            return 0.0;
        }

        return round(($this->success_count / $total) * 100, 2);
    }

    /**
     * Get total runs
     */
    public function getTotalRuns(): int
    {
        return $this->success_count + $this->failure_count;
    }

    /**
     * Get human-readable schedule
     */
    public function getScheduleDescription(): string
    {
        // Parse cron expression and return human-readable format
        $parts = explode(' ', $this->schedule);

        if (count($parts) < 5) {
            return $this->schedule;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        // Common patterns
        if ($this->schedule === '* * * * *') {
            return 'Every minute';
        }
        if ($this->schedule === '0 * * * *') {
            return 'Every hour';
        }
        if ($this->schedule === '0 0 * * *') {
            return 'Daily at midnight';
        }
        if ($this->schedule === '0 0 * * 0') {
            return 'Weekly on Sunday at midnight';
        }
        if ($this->schedule === '0 0 1 * *') {
            return 'Monthly on the 1st at midnight';
        }

        return $this->schedule;
    }

    /**
     * Mark job as successful
     */
    public function markSuccess(): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_status' => 'success',
            'last_error' => null,
            'success_count' => $this->success_count + 1,
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markFailure(string $error): void
    {
        $this->update([
            'last_run_at' => now(),
            'last_status' => 'failed',
            'last_error' => $error,
            'failure_count' => $this->failure_count + 1,
        ]);
    }
}
