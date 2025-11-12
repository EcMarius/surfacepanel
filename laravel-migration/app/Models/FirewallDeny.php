<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FirewallDeny extends Model
{
    use HasFactory;

    protected $table = 'vp_firewall_deny';

    protected $fillable = [
        'ip_address',
        'reason',
        'source',
        'is_permanent',
        'expires_at',
        'violations',
    ];

    protected $casts = [
        'is_permanent' => 'boolean',
        'expires_at' => 'datetime',
        'violations' => 'integer',
    ];

    /**
     * Check if the ban has expired
     */
    public function isExpired(): bool
    {
        if ($this->is_permanent) {
            return false;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Increment violation count
     */
    public function recordViolation(): void
    {
        $this->increment('violations');
        $this->touch();
    }

    /**
     * Block an IP address
     */
    public static function blockIP(string $ip, string $reason, string $source = 'manual', ?int $duration = null): self
    {
        // Check if already blocked
        $existing = self::where('ip_address', $ip)->first();

        if ($existing) {
            $existing->recordViolation();
            return $existing;
        }

        // Create new block
        return self::create([
            'ip_address' => $ip,
            'reason' => $reason,
            'source' => $source,
            'is_permanent' => $duration === null,
            'expires_at' => $duration ? Carbon::now()->addSeconds($duration) : null,
            'violations' => 1,
        ]);
    }

    /**
     * Unblock an IP address
     */
    public static function unblockIP(string $ip): bool
    {
        return self::where('ip_address', $ip)->delete() > 0;
    }

    /**
     * Check if IP is blocked
     */
    public static function isBlocked(string $ip): bool
    {
        $block = self::where('ip_address', $ip)->first();

        if (!$block) {
            return false;
        }

        // Check if expired
        if ($block->isExpired()) {
            $block->delete();
            return false;
        }

        return true;
    }

    /**
     * Clean up expired blocks
     */
    public static function cleanExpired(): int
    {
        return self::where('is_permanent', false)
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }

    /**
     * Get top blocked IPs
     */
    public static function getTopBlocked(int $limit = 10): array
    {
        return self::orderByDesc('violations')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'ip' => $item->ip_address,
                    'violations' => $item->violations,
                    'reason' => $item->reason,
                    'source' => $item->source,
                ];
            })
            ->toArray();
    }
}
