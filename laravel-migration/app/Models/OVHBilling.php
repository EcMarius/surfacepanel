<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OVHBilling extends Model
{
    use HasFactory;

    protected $table = 'vp_ovh_billing';

    protected $fillable = [
        'server_id',
        'bill_id',
        'billing_date',
        'amount',
        'currency',
        'billing_type',
        'description',
        'status',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the server this billing belongs to
     */
    public function server()
    {
        return $this->belongsTo(OVHServer::class, 'server_id');
    }

    /**
     * Check if bill is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if bill is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    /**
     * Mark bill as paid
     */
    public function markAsPaid(): bool
    {
        return $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmount(): string
    {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CAD' => 'C$',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency;

        return $symbol . number_format($this->amount, 2);
    }

    /**
     * Scope for current month billing
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('billing_date', now()->month)
                     ->whereYear('billing_date', now()->year);
    }

    /**
     * Scope for paid bills
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for overdue bills
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope for pending bills
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get total billing for a date range
     */
    public static function getTotalForDateRange($startDate, $endDate, $status = null)
    {
        $query = static::whereBetween('billing_date', [$startDate, $endDate]);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->sum('amount');
    }

    /**
     * Get billing statistics
     */
    public static function getStatistics(int $months = 6): array
    {
        $stats = [];

        for ($i = 0; $i < $months; $i++) {
            $date = now()->subMonths($i);
            $month = $date->format('Y-m');

            $stats[$month] = [
                'total' => static::whereYear('billing_date', $date->year)
                    ->whereMonth('billing_date', $date->month)
                    ->sum('amount'),
                'paid' => static::whereYear('billing_date', $date->year)
                    ->whereMonth('billing_date', $date->month)
                    ->where('status', 'paid')
                    ->sum('amount'),
                'pending' => static::whereYear('billing_date', $date->year)
                    ->whereMonth('billing_date', $date->month)
                    ->where('status', 'pending')
                    ->sum('amount'),
                'overdue' => static::whereYear('billing_date', $date->year)
                    ->whereMonth('billing_date', $date->month)
                    ->where('status', 'overdue')
                    ->sum('amount'),
            ];
        }

        return $stats;
    }
}
