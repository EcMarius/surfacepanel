<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HetznerBilling extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'vp_hetzner_billing';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'resource_type',
        'resource_id',
        'resource_name',
        'billing_date',
        'billing_month',
        'billing_year',
        'hours_used',
        'hourly_rate',
        'amount',
        'currency',
        'details',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'billing_date' => 'date',
        'billing_month' => 'integer',
        'billing_year' => 'integer',
        'hours_used' => 'decimal:2',
        'hourly_rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'details' => 'array',
    ];

    /**
     * Record a billing entry
     */
    public static function recordBilling(
        string $resourceType,
        ?int $resourceId,
        ?string $resourceName,
        float $hoursUsed,
        float $hourlyRate,
        array $details = []
    ): self {
        $now = now();

        return self::create([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_name' => $resourceName,
            'billing_date' => $now->toDateString(),
            'billing_month' => $now->month,
            'billing_year' => $now->year,
            'hours_used' => $hoursUsed,
            'hourly_rate' => $hourlyRate,
            'amount' => $hoursUsed * $hourlyRate,
            'currency' => 'EUR',
            'details' => $details,
        ]);
    }

    /**
     * Get total cost for a period
     */
    public static function getTotalCost(int $year, ?int $month = null): float
    {
        $query = self::where('billing_year', $year);

        if ($month !== null) {
            $query->where('billing_month', $month);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get cost breakdown by resource type
     */
    public static function getCostBreakdown(int $year, int $month): array
    {
        return self::where('billing_year', $year)
            ->where('billing_month', $month)
            ->select('resource_type', DB::raw('SUM(amount) as total_cost'), DB::raw('COUNT(*) as count'))
            ->groupBy('resource_type')
            ->get()
            ->map(function ($item) {
                return [
                    'resource_type' => $item->resource_type,
                    'total_cost' => (float) $item->total_cost,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get monthly costs for the year
     */
    public static function getMonthlyCosts(int $year): array
    {
        $results = self::where('billing_year', $year)
            ->select('billing_month', DB::raw('SUM(amount) as total_cost'))
            ->groupBy('billing_month')
            ->orderBy('billing_month')
            ->get();

        $costs = array_fill(1, 12, 0);

        foreach ($results as $result) {
            $costs[$result->billing_month] = (float) $result->total_cost;
        }

        return $costs;
    }

    /**
     * Get top expensive resources
     */
    public static function getTopExpensive(int $year, int $month, int $limit = 10): array
    {
        return self::where('billing_year', $year)
            ->where('billing_month', $month)
            ->select('resource_type', 'resource_id', 'resource_name', DB::raw('SUM(amount) as total_cost'))
            ->groupBy('resource_type', 'resource_id', 'resource_name')
            ->orderByDesc('total_cost')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'resource_type' => $item->resource_type,
                    'resource_id' => $item->resource_id,
                    'resource_name' => $item->resource_name,
                    'total_cost' => (float) $item->total_cost,
                ];
            })
            ->toArray();
    }

    /**
     * Scope: Current month
     */
    public function scopeCurrentMonth($query)
    {
        $now = now();
        return $query->where('billing_year', $now->year)
            ->where('billing_month', $now->month);
    }

    /**
     * Scope: By resource type
     */
    public function scopeByResourceType($query, string $type)
    {
        return $query->where('resource_type', $type);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }
}
