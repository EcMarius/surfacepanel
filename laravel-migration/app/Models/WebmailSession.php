<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WebmailSession extends Model
{
    use HasFactory;

    protected $table = 'vp_webmail_sessions';

    protected $fillable = [
        'user_id',
        'account_id',
        'email',
        'token',
        'ip_address',
        'user_agent',
        'is_used',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the session
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the account that owns the session
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Generate a secure SSO token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * Create a new SSO token for email login
     */
    public static function createToken(int $userId, int $accountId, string $email): self
    {
        // Clean up expired tokens
        self::cleanupExpired();

        // Create new token
        return self::create([
            'user_id' => $userId,
            'account_id' => $accountId,
            'email' => $email,
            'token' => self::generateToken(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_used' => false,
            'expires_at' => Carbon::now()->addMinutes(5), // Token valid for 5 minutes
        ]);
    }

    /**
     * Validate and consume a token
     */
    public static function validateToken(string $token): ?self
    {
        $session = self::where('token', $token)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($session) {
            // Mark token as used
            $session->update([
                'is_used' => true,
                'used_at' => Carbon::now(),
            ]);

            return $session;
        }

        return null;
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return !$this->is_used && $this->expires_at->isFuture();
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Clean up expired and used tokens
     */
    public static function cleanupExpired(): int
    {
        // Delete tokens older than 1 hour
        return self::where(function ($query) {
            $query->where('expires_at', '<', Carbon::now()->subHour())
                ->orWhere(function ($q) {
                    $q->where('is_used', true)
                        ->where('used_at', '<', Carbon::now()->subHour());
                });
        })->delete();
    }

    /**
     * Get all active sessions for a user
     */
    public static function getActiveSessions(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('user_id', $userId)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Revoke all sessions for a user
     */
    public static function revokeAllForUser(int $userId): int
    {
        return self::where('user_id', $userId)
            ->where('is_used', false)
            ->update([
                'is_used' => true,
                'used_at' => Carbon::now(),
            ]);
    }

    /**
     * Get session statistics
     */
    public static function getStatistics(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);

        return [
            'total_sessions' => self::where('created_at', '>=', $startDate)->count(),
            'used_sessions' => self::where('created_at', '>=', $startDate)
                ->where('is_used', true)
                ->count(),
            'expired_sessions' => self::where('created_at', '>=', $startDate)
                ->where('is_used', false)
                ->where('expires_at', '<', Carbon::now())
                ->count(),
            'active_sessions' => self::where('is_used', false)
                ->where('expires_at', '>', Carbon::now())
                ->count(),
        ];
    }
}
