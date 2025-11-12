<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FirewallLoginFailure extends Model
{
    use HasFactory;

    protected $table = 'vp_firewall_login_failures';

    protected $fillable = [
        'ip_address',
        'service',
        'username',
        'failure_count',
        'first_attempt_at',
        'last_attempt_at',
        'is_banned',
        'banned_until',
    ];

    protected $casts = [
        'failure_count' => 'integer',
        'is_banned' => 'boolean',
        'first_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'banned_until' => 'datetime',
    ];

    /**
     * Record a login failure
     *
     * @param string $ip
     * @param string $service (ssh, ftp, webmail, admin, user)
     * @param string|null $username
     * @return self
     */
    public static function recordFailure(string $ip, string $service, ?string $username = null): self
    {
        $config = FirewallConfig::getInstance();
        $now = Carbon::now();

        // Find or create failure record
        $failure = self::where('ip_address', $ip)
            ->where('service', $service)
            ->where('is_banned', false)
            ->first();

        if ($failure) {
            // Update existing record
            $failure->increment('failure_count');
            $failure->last_attempt_at = $now;
            $failure->username = $username;
            $failure->save();
        } else {
            // Create new record
            $failure = self::create([
                'ip_address' => $ip,
                'service' => $service,
                'username' => $username,
                'failure_count' => 1,
                'first_attempt_at' => $now,
                'last_attempt_at' => $now,
                'is_banned' => false,
            ]);
        }

        // Check if threshold exceeded
        if ($failure->failure_count >= $config->login_failure_threshold) {
            $failure->banIP($config->login_failure_ban_time);
        }

        return $failure;
    }

    /**
     * Ban the IP address
     */
    public function banIP(int $duration): void
    {
        $this->is_banned = true;
        $this->banned_until = Carbon::now()->addSeconds($duration);
        $this->save();

        // Add to firewall deny list
        FirewallDeny::blockIP(
            $this->ip_address,
            "Login failures on {$this->service}: {$this->failure_count} attempts",
            'lfd',
            $duration
        );
    }

    /**
     * Check if IP is still banned
     */
    public function isBanned(): bool
    {
        if (!$this->is_banned) {
            return false;
        }

        if ($this->banned_until && $this->banned_until->isPast()) {
            $this->is_banned = false;
            $this->save();
            return false;
        }

        return true;
    }

    /**
     * Clear login failures for an IP
     */
    public static function clearFailures(string $ip, ?string $service = null): int
    {
        $query = self::where('ip_address', $ip);

        if ($service) {
            $query->where('service', $service);
        }

        return $query->delete();
    }

    /**
     * Get banned IPs
     */
    public static function getBannedIPs(): array
    {
        return self::where('is_banned', true)
            ->where('banned_until', '>', Carbon::now())
            ->orderByDesc('last_attempt_at')
            ->get()
            ->map(function ($item) {
                return [
                    'ip' => $item->ip_address,
                    'service' => $item->service,
                    'failures' => $item->failure_count,
                    'banned_until' => $item->banned_until,
                ];
            })
            ->toArray();
    }

    /**
     * Clean up expired bans
     */
    public static function cleanExpired(): int
    {
        return self::where('is_banned', true)
            ->where('banned_until', '<', Carbon::now())
            ->update(['is_banned' => false]);
    }

    /**
     * Get login failure statistics
     */
    public static function getStatistics(int $days = 7): array
    {
        $since = Carbon::now()->subDays($days);

        $stats = [
            'total_failures' => self::where('created_at', '>=', $since)->sum('failure_count'),
            'unique_ips' => self::where('created_at', '>=', $since)->distinct('ip_address')->count(),
            'banned_ips' => self::where('is_banned', true)->count(),
        ];

        // Failures per service
        $stats['by_service'] = self::selectRaw('service, SUM(failure_count) as count')
            ->where('created_at', '>=', $since)
            ->groupBy('service')
            ->get()
            ->pluck('count', 'service')
            ->toArray();

        return $stats;
    }
}
