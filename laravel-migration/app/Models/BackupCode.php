<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BackupCode extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vp_backup_codes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'code',
        'is_used',
        'used_at',
        'used_ip',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the backup code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the code is still valid
     */
    public function isValid(): bool
    {
        return !$this->is_used;
    }

    /**
     * Mark the code as used
     */
    public function markAsUsed(string $ipAddress): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
            'used_ip' => $ipAddress,
        ]);
    }

    /**
     * Generate backup codes for a user
     *
     * @param User $user
     * @param int $count Number of codes to generate
     * @return array Array of plain text codes (only time they're shown to user)
     */
    public static function generateForUser(User $user, int $count = 10): array
    {
        // Delete old unused codes
        self::where('user_id', $user->id)
            ->where('is_used', false)
            ->delete();

        $plainCodes = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate a random 8-character code
            $plainCode = strtoupper(Str::random(4) . '-' . Str::random(4));
            $plainCodes[] = $plainCode;

            // Store hashed version
            self::create([
                'user_id' => $user->id,
                'code' => Hash::make($plainCode),
                'is_used' => false,
            ]);
        }

        return $plainCodes;
    }

    /**
     * Verify a backup code for a user
     *
     * @param User $user
     * @param string $code
     * @param string $ipAddress
     * @return bool
     */
    public static function verifyForUser(User $user, string $code, string $ipAddress): bool
    {
        $backupCodes = self::where('user_id', $user->id)
            ->where('is_used', false)
            ->get();

        foreach ($backupCodes as $backupCode) {
            if (Hash::check($code, $backupCode->code)) {
                $backupCode->markAsUsed($ipAddress);
                return true;
            }
        }

        return false;
    }

    /**
     * Get remaining backup codes count for a user
     *
     * @param User $user
     * @return int
     */
    public static function remainingForUser(User $user): int
    {
        return self::where('user_id', $user->id)
            ->where('is_used', false)
            ->count();
    }
}
