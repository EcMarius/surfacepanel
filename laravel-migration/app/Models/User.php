<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'username',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * Check if user is root
     */
    public function isRoot(): bool
    {
        return $this->role === 'root';
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['root', 'admin']);
    }

    /**
     * Check if user is reseller
     */
    public function isReseller(): bool
    {
        return $this->role === 'reseller';
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the account associated with the user
     */
    public function account()
    {
        return $this->hasOne(Account::class);
    }

    /**
     * Get the reseller profile if user is a reseller
     */
    public function resellerProfile()
    {
        return $this->hasOne(Reseller::class);
    }

    /**
     * Get the API tokens
     */
    public function apiTokens()
    {
        return $this->hasMany(ApiToken::class);
    }

    /**
     * Get audit logs
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the two-factor authentication settings
     */
    public function twoFactorAuth()
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    /**
     * Get the backup codes
     */
    public function backupCodes()
    {
        return $this->hasMany(BackupCode::class);
    }

    /**
     * Get the two-factor recovery requests
     */
    public function twoFactorRecoveries()
    {
        return $this->hasMany(TwoFactorRecovery::class);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && !empty($this->two_factor_secret);
    }

    /**
     * Get decrypted 2FA secret
     */
    public function getTwoFactorSecret(): ?string
    {
        if (empty($this->two_factor_secret)) {
            return null;
        }

        try {
            return decrypt($this->two_factor_secret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted 2FA secret
     */
    public function setTwoFactorSecret(string $secret): void
    {
        $this->two_factor_secret = encrypt($secret);
    }

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(): void
    {
        $this->two_factor_enabled = true;
        $this->two_factor_confirmed_at = now();
        $this->save();
    }

    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactor(): void
    {
        $this->two_factor_enabled = false;
        $this->two_factor_secret = null;
        $this->two_factor_confirmed_at = null;
        $this->save();

        // Delete all backup codes
        $this->backupCodes()->delete();

        // Delete 2FA settings
        $this->twoFactorAuth()->delete();
    }

    /**
     * Get remaining backup codes count
     */
    public function remainingBackupCodes(): int
    {
        return $this->backupCodes()
            ->where('is_used', false)
            ->count();
    }
}
