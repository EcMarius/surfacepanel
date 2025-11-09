<?php

namespace VirPanel\Models;

use DateTime;

/**
 * Account Model
 *
 * Represents cPanel user accounts
 */
class Account
{
    protected ?int $id = null;
    protected ?int $userId = null;
    protected ?int $resellerId = null;
    protected int $packageId;
    protected string $username;
    protected string $domain;
    protected string $email;
    protected string $password;
    protected string $homeDirectory;
    protected int $diskUsed = 0;
    protected int $diskQuota = 0;
    protected int $bandwidthUsed = 0;
    protected int $bandwidthQuota = 0;
    protected int $inodesUsed = 0;
    protected int $inodesQuota = 0;
    protected string $status = 'active'; // active, suspended, terminated
    protected ?string $suspensionReason = null;
    protected ?DateTime $suspendedAt = null;
    protected bool $shellAccess = false;
    protected string $shell = '/bin/bash';
    protected ?string $contactEmail = null;
    protected ?string $ipAddress = null;
    protected bool $dedicatedIp = false;
    protected string $theme = 'default';
    protected string $locale = 'en_US';
    protected string $timezone = 'UTC';
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;
    protected ?DateTime $deletedAt = null;

    // Relationships
    protected ?User $user = null;
    protected ?User $reseller = null;
    protected ?Package $package = null;
    protected array $domains = [];
    protected array $emailAccounts = [];
    protected array $databases = [];

    /**
     * Get the account ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set username
     *
     * @param string $username
     * @return $this
     */
    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Get domain
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Set domain
     *
     * @param string $domain
     * @return $this
     */
    public function setDomain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return $this
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Set password
     *
     * @param string $password
     * @return $this
     */
    public function setPassword(string $password): static
    {
        $this->password = password_hash($password, PASSWORD_ARGON2ID);
        return $this;
    }

    /**
     * Verify password
     *
     * @param string $password
     * @return bool
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * Get home directory
     *
     * @return string
     */
    public function getHomeDirectory(): string
    {
        return $this->homeDirectory;
    }

    /**
     * Set home directory
     *
     * @param string $homeDirectory
     * @return $this
     */
    public function setHomeDirectory(string $homeDirectory): static
    {
        $this->homeDirectory = $homeDirectory;
        return $this;
    }

    /**
     * Get disk used (MB)
     *
     * @return int
     */
    public function getDiskUsed(): int
    {
        return $this->diskUsed;
    }

    /**
     * Set disk used
     *
     * @param int $diskUsed
     * @return $this
     */
    public function setDiskUsed(int $diskUsed): static
    {
        $this->diskUsed = $diskUsed;
        return $this;
    }

    /**
     * Get disk quota (MB, 0 = unlimited)
     *
     * @return int
     */
    public function getDiskQuota(): int
    {
        return $this->diskQuota;
    }

    /**
     * Check if disk quota is unlimited
     *
     * @return bool
     */
    public function hasUnlimitedDisk(): bool
    {
        return $this->diskQuota === 0;
    }

    /**
     * Get disk usage percentage
     *
     * @return float
     */
    public function getDiskUsagePercentage(): float
    {
        if ($this->diskQuota === 0) {
            return 0;
        }

        return ($this->diskUsed / $this->diskQuota) * 100;
    }

    /**
     * Check if over disk quota
     *
     * @return bool
     */
    public function isOverDiskQuota(): bool
    {
        if ($this->diskQuota === 0) {
            return false;
        }

        return $this->diskUsed > $this->diskQuota;
    }

    /**
     * Get bandwidth used (MB)
     *
     * @return int
     */
    public function getBandwidthUsed(): int
    {
        return $this->bandwidthUsed;
    }

    /**
     * Get bandwidth quota (MB, 0 = unlimited)
     *
     * @return int
     */
    public function getBandwidthQuota(): int
    {
        return $this->bandwidthQuota;
    }

    /**
     * Check if bandwidth quota is unlimited
     *
     * @return bool
     */
    public function hasUnlimitedBandwidth(): bool
    {
        return $this->bandwidthQuota === 0;
    }

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Check if account is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is suspended
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Suspend account
     *
     * @param string $reason
     * @return $this
     */
    public function suspend(string $reason): static
    {
        $this->status = 'suspended';
        $this->suspensionReason = $reason;
        $this->suspendedAt = new DateTime();
        return $this;
    }

    /**
     * Unsuspend account
     *
     * @return $this
     */
    public function unsuspend(): static
    {
        $this->status = 'active';
        $this->suspensionReason = null;
        $this->suspendedAt = null;
        return $this;
    }

    /**
     * Check if shell access is enabled
     *
     * @return bool
     */
    public function hasShellAccess(): bool
    {
        return $this->shellAccess;
    }

    /**
     * Set shell access
     *
     * @param bool $shellAccess
     * @return $this
     */
    public function setShellAccess(bool $shellAccess): static
    {
        $this->shellAccess = $shellAccess;
        return $this;
    }

    /**
     * Get package
     *
     * @return Package|null
     */
    public function getPackage(): ?Package
    {
        return $this->package;
    }

    /**
     * Set package
     *
     * @param Package $package
     * @return $this
     */
    public function setPackage(Package $package): static
    {
        $this->package = $package;
        $this->packageId = $package->getId();
        return $this;
    }

    /**
     * Get domains
     *
     * @return array
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    /**
     * Add domain
     *
     * @param Domain $domain
     * @return $this
     */
    public function addDomain(Domain $domain): static
    {
        $this->domains[] = $domain;
        return $this;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'domain' => $this->domain,
            'email' => $this->email,
            'home_directory' => $this->homeDirectory,
            'disk_used' => $this->diskUsed,
            'disk_quota' => $this->diskQuota,
            'bandwidth_used' => $this->bandwidthUsed,
            'bandwidth_quota' => $this->bandwidthQuota,
            'status' => $this->status,
            'shell_access' => $this->shellAccess,
            'ip_address' => $this->ipAddress,
            'dedicated_ip' => $this->dedicatedIp,
            'theme' => $this->theme,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
