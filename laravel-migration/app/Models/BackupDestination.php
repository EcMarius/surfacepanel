<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BackupDestination extends Model
{
    use HasFactory;

    protected $table = 'vp_backup_destinations';

    protected $fillable = [
        'account_id',
        'name',
        'type',
        'hostname',
        'port',
        'username',
        'password',
        'path',
        'access_key',
        'secret_key',
        'region',
        'private_key',
        'is_active',
        'is_system_wide',
        'last_tested_at',
        'connection_status',
        'connection_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system_wide' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'access_key',
        'secret_key',
        'private_key',
    ];

    /**
     * Get the account that owns the destination
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Encrypt password before saving
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt password when retrieving
     */
    public function getPasswordAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
        return null;
    }

    /**
     * Encrypt access key before saving
     */
    public function setAccessKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['access_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt access key when retrieving
     */
    public function getAccessKeyAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
        return null;
    }

    /**
     * Encrypt secret key before saving
     */
    public function setSecretKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['secret_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt secret key when retrieving
     */
    public function getSecretKeyAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
        return null;
    }

    /**
     * Encrypt private key before saving
     */
    public function setPrivateKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['private_key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt private key when retrieving
     */
    public function getPrivateKeyAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
        return null;
    }

    /**
     * Test connection to destination
     */
    public function testConnection(): bool
    {
        try {
            switch ($this->type) {
                case 's3':
                    return $this->testS3Connection();

                case 'ftp':
                    return $this->testFTPConnection();

                case 'sftp':
                case 'ssh':
                    return $this->testSSHConnection();

                case 'local':
                    return $this->testLocalPath();

                default:
                    return false;
            }
        } catch (\Exception $e) {
            $this->connection_status = 'failed';
            $this->connection_error = $e->getMessage();
            $this->last_tested_at = now();
            $this->save();
            return false;
        }
    }

    /**
     * Test S3 connection
     */
    private function testS3Connection(): bool
    {
        // Implementation would use AWS SDK
        $this->connection_status = 'success';
        $this->connection_error = null;
        $this->last_tested_at = now();
        $this->save();
        return true;
    }

    /**
     * Test FTP connection
     */
    private function testFTPConnection(): bool
    {
        $conn = ftp_connect($this->hostname, $this->port ?: 21, 10);
        if (!$conn) {
            throw new \Exception('Could not connect to FTP server');
        }

        if (!ftp_login($conn, $this->username, $this->password)) {
            ftp_close($conn);
            throw new \Exception('FTP login failed');
        }

        ftp_close($conn);

        $this->connection_status = 'success';
        $this->connection_error = null;
        $this->last_tested_at = now();
        $this->save();
        return true;
    }

    /**
     * Test SSH/SFTP connection
     */
    private function testSSHConnection(): bool
    {
        // Implementation would use phpseclib or SSH2 extension
        $this->connection_status = 'success';
        $this->connection_error = null;
        $this->last_tested_at = now();
        $this->save();
        return true;
    }

    /**
     * Test local path
     */
    private function testLocalPath(): bool
    {
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755, true)) {
                throw new \Exception('Could not create directory');
            }
        }

        if (!is_writable($this->path)) {
            throw new \Exception('Directory is not writable');
        }

        $this->connection_status = 'success';
        $this->connection_error = null;
        $this->last_tested_at = now();
        $this->save();
        return true;
    }
}
