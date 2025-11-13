<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ModalUsage extends Model
{
    use HasFactory;

    protected $table = 'vp_modal_usage';

    protected $fillable = [
        'account_id',
        'function_id',
        'usage_date',
        'total_invocations',
        'successful_invocations',
        'failed_invocations',
        'total_execution_time_ms',
        'total_cpu_time_ms',
        'total_gpu_time_ms',
        'total_memory_mb_seconds',
        'estimated_cost',
        'detailed_metrics',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'total_invocations' => 'integer',
        'successful_invocations' => 'integer',
        'failed_invocations' => 'integer',
        'total_execution_time_ms' => 'integer',
        'total_cpu_time_ms' => 'integer',
        'total_gpu_time_ms' => 'integer',
        'total_memory_mb_seconds' => 'integer',
        'estimated_cost' => 'decimal:4',
        'detailed_metrics' => 'array',
    ];

    /**
     * Get the account that owns this usage record
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the function associated with this usage record
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ModalFunction::class, 'function_id');
    }

    /**
     * Scope for usage by account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope for usage by function
     */
    public function scopeForFunction($query, $functionId)
    {
        return $query->where('function_id', $functionId);
    }

    /**
     * Scope for usage in date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('usage_date', [$startDate, $endDate]);
    }

    /**
     * Scope for current month
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereYear('usage_date', Carbon::now()->year)
                     ->whereMonth('usage_date', Carbon::now()->month);
    }

    /**
     * Scope for last N days
     */
    public function scopeLastDays($query, int $days = 30)
    {
        return $query->where('usage_date', '>=', Carbon::now()->subDays($days)->startOfDay());
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRate(): float
    {
        if ($this->total_invocations === 0) {
            return 0.0;
        }

        return round(($this->successful_invocations / $this->total_invocations) * 100, 2);
    }

    /**
     * Get average execution time in seconds
     */
    public function getAverageExecutionTimeSeconds(): float
    {
        if ($this->total_invocations === 0) {
            return 0.0;
        }

        return round($this->total_execution_time_ms / $this->total_invocations / 1000, 2);
    }

    /**
     * Get GPU hours used
     */
    public function getGPUHours(): float
    {
        return round($this->total_gpu_time_ms / 1000 / 3600, 4);
    }

    /**
     * Get CPU hours used
     */
    public function getCPUHours(): float
    {
        return round($this->total_cpu_time_ms / 1000 / 3600, 4);
    }

    /**
     * Get memory GB hours
     */
    public function getMemoryGBHours(): float
    {
        return round($this->total_memory_mb_seconds / 1024 / 3600, 4);
    }

    /**
     * Get aggregate usage statistics for account
     */
    public static function getAccountStatistics($accountId, $days = 30): array
    {
        $usage = self::forAccount($accountId)
            ->lastDays($days)
            ->get();

        return [
            'total_invocations' => $usage->sum('total_invocations'),
            'successful_invocations' => $usage->sum('successful_invocations'),
            'failed_invocations' => $usage->sum('failed_invocations'),
            'total_cost' => $usage->sum('estimated_cost'),
            'total_gpu_hours' => round($usage->sum('total_gpu_time_ms') / 1000 / 3600, 4),
            'total_cpu_hours' => round($usage->sum('total_cpu_time_ms') / 1000 / 3600, 4),
            'success_rate' => $usage->sum('total_invocations') > 0
                ? round(($usage->sum('successful_invocations') / $usage->sum('total_invocations')) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get daily usage chart data
     */
    public static function getDailyChartData($accountId, $days = 30): array
    {
        $usage = self::forAccount($accountId)
            ->lastDays($days)
            ->orderBy('usage_date', 'asc')
            ->get();

        return [
            'labels' => $usage->pluck('usage_date')->map(fn($date) => $date->format('M d'))->toArray(),
            'invocations' => $usage->pluck('total_invocations')->toArray(),
            'costs' => $usage->pluck('estimated_cost')->toArray(),
            'success_rate' => $usage->map(function($item) {
                return $item->getSuccessRate();
            })->toArray(),
        ];
    }
}
