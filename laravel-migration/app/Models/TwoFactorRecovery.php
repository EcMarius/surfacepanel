<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TwoFactorRecovery extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_two_factor_recovery';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'ip_address',
        'user_agent',
        'status',
        'expires_at',
        'approved_at',
        'approved_by',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user that requested recovery.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved the recovery.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if recovery request is still valid
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' &&
               $this->expires_at->isFuture();
    }

    /**
     * Approve the recovery request
     */
    public function approve(User $approver, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $approver->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Deny the recovery request
     */
    public function deny(User $denier, ?string $notes = null): void
    {
        $this->update([
            'status' => 'denied',
            'approved_at' => now(),
            'approved_by' => $denier->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Mark as expired
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }

    /**
     * Create a new recovery request for a user
     */
    public static function createForUser(
        User $user,
        string $ipAddress,
        ?string $userAgent = null,
        int $expiresInHours = 24
    ): self {
        // Mark any existing pending requests as expired
        self::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        return self::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => 'pending',
            'expires_at' => now()->addHours($expiresInHours),
        ]);
    }
}
