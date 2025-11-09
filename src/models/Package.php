<?php

namespace VirPanel\Models;

use DateTime;

/**
 * Package Model
 *
 * Represents hosting packages/plans
 */
class Package
{
    protected ?int $id = null;
    protected ?int $resellerId = null;
    protected string $name;
    protected string $displayName;
    protected ?string $description = null;
    protected int $diskQuota = 0; // MB, 0 = unlimited
    protected int $bandwidthQuota = 0; // MB, 0 = unlimited
    protected int $inodesQuota = 0; // 0 = unlimited
    protected int $maxAddonDomains = 0; // 0 = unlimited
    protected int $maxParkedDomains = 0;
    protected int $maxSubdomains = 0;
    protected int $maxEmailAccounts = 0;
    protected int $maxEmailLists = 0;
    protected int $maxDatabases = 0;
    protected int $maxFtpAccounts = 0;
    protected bool $shellAccess = false;
    protected bool $cgiAccess = true;
    protected bool $dedicatedIp = false;
    protected bool $privateNameservers = false;
    protected bool $sslSupport = true;
    protected bool $wildcardSsl = false;
    protected int $maxHourlyEmails = 0; // 0 = unlimited
    protected int $maxDailyEmails = 0;
    protected bool $backupEnabled = true;
    protected int $backupRetentionDays = 7;
    protected bool $autosslEnabled = true;
    protected ?array $phpVersions = null;
    protected ?array $features = null;
    protected float $monthlyPrice = 0;
    protected bool $isActive = true;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;
    protected ?DateTime $deletedAt = null;

    // Relationships
    protected array $accounts = [];

    /**
     * Get the package ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get display name
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Set display name
     *
     * @param string $displayName
     * @return $this
     */
    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set description
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get disk quota (MB)
     *
     * @return int
     */
    public function getDiskQuota(): int
    {
        return $this->diskQuota;
    }

    /**
     * Set disk quota
     *
     * @param int $diskQuota
     * @return $this
     */
    public function setDiskQuota(int $diskQuota): static
    {
        $this->diskQuota = $diskQuota;
        return $this;
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
     * Get bandwidth quota (MB)
     *
     * @return int
     */
    public function getBandwidthQuota(): int
    {
        return $this->bandwidthQuota;
    }

    /**
     * Set bandwidth quota
     *
     * @param int $bandwidthQuota
     * @return $this
     */
    public function setBandwidthQuota(int $bandwidthQuota): static
    {
        $this->bandwidthQuota = $bandwidthQuota;
        return $this;
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
     * Get max addon domains
     *
     * @return int
     */
    public function getMaxAddonDomains(): int
    {
        return $this->maxAddonDomains;
    }

    /**
     * Set max addon domains
     *
     * @param int $maxAddonDomains
     * @return $this
     */
    public function setMaxAddonDomains(int $maxAddonDomains): static
    {
        $this->maxAddonDomains = $maxAddonDomains;
        return $this;
    }

    /**
     * Check if addon domains are unlimited
     *
     * @return bool
     */
    public function hasUnlimitedAddonDomains(): bool
    {
        return $this->maxAddonDomains === 0;
    }

    /**
     * Get max email accounts
     *
     * @return int
     */
    public function getMaxEmailAccounts(): int
    {
        return $this->maxEmailAccounts;
    }

    /**
     * Set max email accounts
     *
     * @param int $maxEmailAccounts
     * @return $this
     */
    public function setMaxEmailAccounts(int $maxEmailAccounts): static
    {
        $this->maxEmailAccounts = $maxEmailAccounts;
        return $this;
    }

    /**
     * Check if email accounts are unlimited
     *
     * @return bool
     */
    public function hasUnlimitedEmailAccounts(): bool
    {
        return $this->maxEmailAccounts === 0;
    }

    /**
     * Get max databases
     *
     * @return int
     */
    public function getMaxDatabases(): int
    {
        return $this->maxDatabases;
    }

    /**
     * Set max databases
     *
     * @param int $maxDatabases
     * @return $this
     */
    public function setMaxDatabases(int $maxDatabases): static
    {
        $this->maxDatabases = $maxDatabases;
        return $this;
    }

    /**
     * Check if databases are unlimited
     *
     * @return bool
     */
    public function hasUnlimitedDatabases(): bool
    {
        return $this->maxDatabases === 0;
    }

    /**
     * Check if shell access is allowed
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
     * Check if SSL is supported
     *
     * @return bool
     */
    public function hasSslSupport(): bool
    {
        return $this->sslSupport;
    }

    /**
     * Set SSL support
     *
     * @param bool $sslSupport
     * @return $this
     */
    public function setSslSupport(bool $sslSupport): static
    {
        $this->sslSupport = $sslSupport;
        return $this;
    }

    /**
     * Check if backups are enabled
     *
     * @return bool
     */
    public function hasBackupEnabled(): bool
    {
        return $this->backupEnabled;
    }

    /**
     * Set backup enabled
     *
     * @param bool $backupEnabled
     * @return $this
     */
    public function setBackupEnabled(bool $backupEnabled): static
    {
        $this->backupEnabled = $backupEnabled;
        return $this;
    }

    /**
     * Get PHP versions
     *
     * @return array|null
     */
    public function getPhpVersions(): ?array
    {
        return $this->phpVersions;
    }

    /**
     * Set PHP versions
     *
     * @param array|null $phpVersions
     * @return $this
     */
    public function setPhpVersions(?array $phpVersions): static
    {
        $this->phpVersions = $phpVersions;
        return $this;
    }

    /**
     * Get additional features
     *
     * @return array|null
     */
    public function getFeatures(): ?array
    {
        return $this->features;
    }

    /**
     * Set additional features
     *
     * @param array|null $features
     * @return $this
     */
    public function setFeatures(?array $features): static
    {
        $this->features = $features;
        return $this;
    }

    /**
     * Get monthly price
     *
     * @return float
     */
    public function getMonthlyPrice(): float
    {
        return $this->monthlyPrice;
    }

    /**
     * Set monthly price
     *
     * @param float $monthlyPrice
     * @return $this
     */
    public function setMonthlyPrice(float $monthlyPrice): static
    {
        $this->monthlyPrice = $monthlyPrice;
        return $this;
    }

    /**
     * Check if package is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Set active status
     *
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Get accounts using this package
     *
     * @return array
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    /**
     * Add account
     *
     * @param Account $account
     * @return $this
     */
    public function addAccount(Account $account): static
    {
        $this->accounts[] = $account;
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
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'disk_quota' => $this->diskQuota,
            'bandwidth_quota' => $this->bandwidthQuota,
            'inodes_quota' => $this->inodesQuota,
            'max_addon_domains' => $this->maxAddonDomains,
            'max_parked_domains' => $this->maxParkedDomains,
            'max_subdomains' => $this->maxSubdomains,
            'max_email_accounts' => $this->maxEmailAccounts,
            'max_databases' => $this->maxDatabases,
            'max_ftp_accounts' => $this->maxFtpAccounts,
            'shell_access' => $this->shellAccess,
            'ssl_support' => $this->sslSupport,
            'backup_enabled' => $this->backupEnabled,
            'php_versions' => $this->phpVersions,
            'monthly_price' => $this->monthlyPrice,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
