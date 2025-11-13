<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpamScore extends Model
{
    use HasFactory;

    protected $table = 'vp_spam_scores';

    protected $fillable = [
        'account_id',
        'email_from',
        'email_to',
        'subject',
        'spam_score',
        'required_score',
        'is_spam',
        'action_taken',
        'tests_hit',
        'message_id',
    ];

    protected $casts = [
        'spam_score' => 'decimal:2',
        'required_score' => 'decimal:2',
        'is_spam' => 'boolean',
    ];

    /**
     * Get the account that owns this spam score
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope to get spam emails only
     */
    public function scopeSpam($query)
    {
        return $query->where('is_spam', true);
    }

    /**
     * Scope to get ham (legitimate) emails only
     */
    public function scopeHam($query)
    {
        return $query->where('is_spam', false);
    }

    /**
     * Scope to get emails for a specific account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to get emails from a specific date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get score color based on spam score
     */
    public function getScoreColor(): string
    {
        if ($this->spam_score >= 10) {
            return 'bg-red-600';
        } elseif ($this->spam_score >= 7) {
            return 'bg-red-500';
        } elseif ($this->spam_score >= 5) {
            return 'bg-orange-500';
        } elseif ($this->spam_score >= 3) {
            return 'bg-yellow-500';
        } else {
            return 'bg-green-500';
        }
    }

    /**
     * Get action badge color
     */
    public function getActionColor(): string
    {
        return match($this->action_taken) {
            'deleted' => 'bg-red-600',
            'quarantined' => 'bg-orange-600',
            'tagged' => 'bg-blue-600',
            'delivered' => 'bg-green-600',
            default => 'bg-gray-600',
        };
    }

    /**
     * Get statistics for an account
     */
    public static function getStatistics($accountId, $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);

        $query = self::forAccount($accountId)
            ->where('created_at', '>=', $startDate);

        $total = $query->count();
        $spam = $query->clone()->spam()->count();
        $ham = $query->clone()->ham()->count();
        $deleted = $query->clone()->where('action_taken', 'deleted')->count();
        $quarantined = $query->clone()->where('action_taken', 'quarantined')->count();
        $tagged = $query->clone()->where('action_taken', 'tagged')->count();

        $avgSpamScore = $query->clone()->spam()->avg('spam_score') ?? 0;
        $avgHamScore = $query->clone()->ham()->avg('spam_score') ?? 0;

        return [
            'total' => $total,
            'spam' => $spam,
            'ham' => $ham,
            'deleted' => $deleted,
            'quarantined' => $quarantined,
            'tagged' => $tagged,
            'spam_percentage' => $total > 0 ? round(($spam / $total) * 100, 2) : 0,
            'average_spam_score' => round($avgSpamScore, 2),
            'average_ham_score' => round($avgHamScore, 2),
        ];
    }

    /**
     * Get top spam senders
     */
    public static function getTopSpamSenders($accountId, $limit = 10): array
    {
        return self::forAccount($accountId)
            ->spam()
            ->selectRaw('email_from, COUNT(*) as count')
            ->whereNotNull('email_from')
            ->groupBy('email_from')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'email' => $item->email_from,
                    'count' => $item->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get spam trend (daily count for last N days)
     */
    public static function getSpamTrend($accountId, $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($days - $i - 1)->startOfDay();
            $nextDate = $date->copy()->addDay();

            $spam = self::forAccount($accountId)
                ->spam()
                ->whereBetween('created_at', [$date, $nextDate])
                ->count();

            $data[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $spam,
            ];
        }

        return $data;
    }

    /**
     * Get most common spam tests triggered
     */
    public function getTestsArray(): array
    {
        if (empty($this->tests_hit)) {
            return [];
        }

        return explode(',', $this->tests_hit);
    }

    /**
     * Format tests hit for display
     */
    public function getFormattedTests(): string
    {
        $tests = $this->getTestsArray();

        if (empty($tests)) {
            return 'None';
        }

        return implode(', ', array_slice($tests, 0, 5)) .
               (count($tests) > 5 ? ' and ' . (count($tests) - 5) . ' more' : '');
    }
}
