<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class WAFLog extends Model
{
    use HasFactory;

    protected $table = 'vp_waf_logs';

    protected $fillable = [
        'account_id',
        'domain',
        'client_ip',
        'request_method',
        'request_uri',
        'user_agent',
        'attack_type',
        'rule_id',
        'severity',
        'action',
        'request_headers',
        'request_body',
        'matched_data',
        'country_code',
    ];

    protected $casts = [
        'severity' => 'integer',
    ];

    /**
     * Get the account that owns this log
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get severity level name
     */
    public function getSeverityName(): string
    {
        return match($this->severity) {
            1 => 'Critical',
            2 => 'High',
            3 => 'Medium',
            4 => 'Low',
            5 => 'Info',
            default => 'Unknown',
        };
    }

    /**
     * Get severity color class for UI
     */
    public function getSeverityColor(): string
    {
        return match($this->severity) {
            1 => 'bg-red-600',
            2 => 'bg-orange-600',
            3 => 'bg-yellow-600',
            4 => 'bg-blue-600',
            5 => 'bg-gray-600',
            default => 'bg-gray-400',
        };
    }

    /**
     * Scope: Recent logs
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope: By account
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope: By attack type
     */
    public function scopeByAttackType(Builder $query, string $type): Builder
    {
        return $query->where('attack_type', $type);
    }

    /**
     * Scope: Blocked only
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('action', 'blocked');
    }

    /**
     * Scope: Critical severity
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->whereIn('severity', [1, 2]);
    }

    /**
     * Get top attacking IPs
     */
    public static function getTopAttackingIPs(int $limit = 10, ?int $accountId = null): array
    {
        $query = self::selectRaw('client_ip, COUNT(*) as count')
            ->groupBy('client_ip')
            ->orderByDesc('count')
            ->limit($limit);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->get()->map(function($item) {
            return [
                'ip' => $item->client_ip,
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get top attacked URLs
     */
    public static function getTopAttackedURLs(int $limit = 10, ?int $accountId = null): array
    {
        $query = self::selectRaw('request_uri, COUNT(*) as count')
            ->groupBy('request_uri')
            ->orderByDesc('count')
            ->limit($limit);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->get()->map(function($item) {
            return [
                'uri' => $item->request_uri,
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get attack statistics
     */
    public static function getStatistics(?int $accountId = null, int $days = 7): array
    {
        $query = self::where('created_at', '>=', Carbon::now()->subDays($days));

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $total = $query->count();
        $blocked = $query->clone()->where('action', 'blocked')->count();

        return [
            'total' => $total,
            'blocked' => $blocked,
            'detection_only' => $total - $blocked,
            'sql_injection' => $query->clone()->where('attack_type', 'sql_injection')->count(),
            'xss' => $query->clone()->where('attack_type', 'xss')->count(),
            'lfi' => $query->clone()->where('attack_type', 'lfi')->count(),
            'rfi' => $query->clone()->where('attack_type', 'rfi')->count(),
            'rce' => $query->clone()->where('attack_type', 'rce')->count(),
        ];
    }
}
