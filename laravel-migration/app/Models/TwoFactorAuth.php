<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorAuth extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_two_factor_auth';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'is_enabled',
        'secret',
        'enabled_at',
        'last_used_at',
        'last_used_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the two-factor authentication.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if 2FA is active
     */
    public function isActive(): bool
    {
        return $this->is_enabled && !empty($this->secret);
    }

    /**
     * Get decrypted secret
     */
    public function getDecryptedSecret(): ?string
    {
        if (empty($this->secret)) {
            return null;
        }

        try {
            return decrypt($this->secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted secret
     */
    public function setSecret(string $secret): void
    {
        $this->secret = encrypt($secret);
    }

    /**
     * Record successful authentication
     */
    public function recordUsage(string $ipAddress): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ipAddress,
        ]);
    }

    /**
     * Enable 2FA
     */
    public function enable(): void
    {
        $this->update([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(): void
    {
        $this->update([
            'is_enabled' => false,
        ]);
    }
}
