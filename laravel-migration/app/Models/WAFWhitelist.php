<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WAFWhitelist extends Model
{
    use HasFactory;

    protected $table = 'vp_waf_whitelist';

    protected $fillable = [
        'account_id',
        'type',
        'value',
        'description',
        'is_global',
        'expires_at',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if entry has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if value matches the whitelist entry
     */
    public function matches(string $value): bool
    {
        return match($this->type) {
            'ip' => $this->value === $value,
            'ip_range' => $this->matchesIPRange($value),
            'user_agent' => str_contains($value, $this->value),
            'uri_pattern' => preg_match($this->value, $value),
            default => false,
        };
    }

    /**
     * Check if IP matches CIDR range
     */
    private function matchesIPRange(string $ip): bool
    {
        if (!str_contains($this->value, '/')) {
            return false;
        }

        [$range, $netmask] = explode('/', $this->value);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;

        return ($ipDecimal & $netmaskDecimal) == ($rangeDecimal & $netmaskDecimal);
    }
}
