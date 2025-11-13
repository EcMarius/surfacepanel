<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BackupEncryption extends Model
{
    use HasFactory;

    protected $table = 'vp_backup_encryption';

    protected $fillable = [
        'account_id',
        'name',
        'method',
        'encryption_key',
        'iv',
        'is_active',
        'is_default',
        'is_system_wide',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_system_wide' => 'boolean',
    ];

    protected $hidden = [
        'encryption_key',
        'iv',
    ];

    /**
     * Get the account that owns the encryption configuration
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get schedules using this encryption
     */
    public function schedules()
    {
        return $this->hasMany(BackupSchedule::class, 'encryption_id');
    }

    /**
     * Encrypt the encryption key before saving
     */
    public function setEncryptionKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['encryption_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt the encryption key when retrieving
     */
    public function getEncryptionKeyAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
        return null;
    }

    /**
     * Encrypt the IV before saving
     */
    public function setIvAttribute($value)
    {
        if ($value) {
            $this->attributes['iv'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt the IV when retrieving
     */
    public function getIvAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
        return null;
    }

    /**
     * Generate a new encryption key
     */
    public static function generateKey(string $method = 'aes-256-cbc'): string
    {
        $length = $method === 'aes-256-cbc' || $method === 'aes-256-gcm' ? 32 : 16;
        return random_bytes($length);
    }

    /**
     * Generate a new IV
     */
    public static function generateIV(string $method = 'aes-256-cbc'): string
    {
        $length = openssl_cipher_iv_length($method);
        return random_bytes($length);
    }

    /**
     * Encrypt a file
     */
    public function encryptFile(string $sourceFile, string $destinationFile): bool
    {
        try {
            $key = $this->encryption_key;
            $iv = $this->iv;

            if (!$key || !$iv) {
                throw new \Exception('Encryption key or IV not set');
            }

            $data = file_get_contents($sourceFile);
            $encrypted = openssl_encrypt($data, $this->method, $key, OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                throw new \Exception('Encryption failed');
            }

            // Prepend IV to encrypted data for decryption
            $encryptedData = $iv . $encrypted;

            return file_put_contents($destinationFile, $encryptedData) !== false;
        } catch (\Exception $e) {
            throw new \Exception('File encryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt a file
     */
    public function decryptFile(string $sourceFile, string $destinationFile): bool
    {
        try {
            $key = $this->encryption_key;

            if (!$key) {
                throw new \Exception('Encryption key not set');
            }

            $encryptedData = file_get_contents($sourceFile);
            $ivLength = openssl_cipher_iv_length($this->method);

            // Extract IV from the beginning of the file
            $iv = substr($encryptedData, 0, $ivLength);
            $encrypted = substr($encryptedData, $ivLength);

            $decrypted = openssl_decrypt($encrypted, $this->method, $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                throw new \Exception('Decryption failed');
            }

            return file_put_contents($destinationFile, $decrypted) !== false;
        } catch (\Exception $e) {
            throw new \Exception('File decryption failed: ' . $e->getMessage());
        }
    }

    /**
     * Set as default encryption for account
     */
    public function setAsDefault(): void
    {
        // Remove default flag from other encryptions for this account
        self::where('account_id', $this->account_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }
}
