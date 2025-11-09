<?php

namespace VirPanel\Models;

use DateTime;

/**
 * ResellerSettings Model
 *
 * Represents reseller-specific settings and limits
 */
class ResellerSettings
{
    protected ?int $id = null;
    protected int $userId;
    protected ?string $companyName = null;
    protected ?string $companyLogo = null;
    protected ?string $companyAddress = null;
    protected ?string $supportEmail = null;
    protected ?string $supportPhone = null;
    protected ?string $supportUrl = null;
    protected int $maxAccounts = 0; // 0 = unlimited
    protected int $diskQuota = 0; // MB, 0 = unlimited
    protected int $bandwidthQuota = 0; // MB, 0 = unlimited
    protected bool $canCreatePackages = true;
    protected bool $canOversell = false;
    protected bool $whiteLabel = false;
    protected ?string $nameserver1 = null;
    protected ?string $nameserver2 = null;
    protected ?string $nameserver3 = null;
    protected ?string $nameserver4 = null;
    protected ?array $allowedFeatures = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    // Relationships
    protected ?User $user = null;

    /**
     * Get the ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get company name
     *
     * @return string|null
     */
    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    /**
     * Set company name
     *
     * @param string|null $companyName
     * @return $this
     */
    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    /**
     * Get company logo
     *
     * @return string|null
     */
    public function getCompanyLogo(): ?string
    {
        return $this->companyLogo;
    }

    /**
     * Set company logo
     *
     * @param string|null $companyLogo
     * @return $this
     */
    public function setCompanyLogo(?string $companyLogo): static
    {
        $this->companyLogo = $companyLogo;
        return $this;
    }

    /**
     * Get support email
     *
     * @return string|null
     */
    public function getSupportEmail(): ?string
    {
        return $this->supportEmail;
    }

    /**
     * Set support email
     *
     * @param string|null $supportEmail
     * @return $this
     */
    public function setSupportEmail(?string $supportEmail): static
    {
        $this->supportEmail = $supportEmail;
        return $this;
    }

    /**
     * Get max accounts allowed
     *
     * @return int
     */
    public function getMaxAccounts(): int
    {
        return $this->maxAccounts;
    }

    /**
     * Set max accounts
     *
     * @param int $maxAccounts
     * @return $this
     */
    public function setMaxAccounts(int $maxAccounts): static
    {
        $this->maxAccounts = $maxAccounts;
        return $this;
    }

    /**
     * Check if accounts are unlimited
     *
     * @return bool
     */
    public function hasUnlimitedAccounts(): bool
    {
        return $this->maxAccounts === 0;
    }

    /**
     * Get disk quota
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
     * Check if disk is unlimited
     *
     * @return bool
     */
    public function hasUnlimitedDisk(): bool
    {
        return $this->diskQuota === 0;
    }

    /**
     * Get bandwidth quota
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
     * Check if bandwidth is unlimited
     *
     * @return bool
     */
    public function hasUnlimitedBandwidth(): bool
    {
        return $this->bandwidthQuota === 0;
    }

    /**
     * Check if reseller can create packages
     *
     * @return bool
     */
    public function canCreatePackages(): bool
    {
        return $this->canCreatePackages;
    }

    /**
     * Set can create packages
     *
     * @param bool $canCreatePackages
     * @return $this
     */
    public function setCanCreatePackages(bool $canCreatePackages): static
    {
        $this->canCreatePackages = $canCreatePackages;
        return $this;
    }

    /**
     * Check if reseller can oversell
     *
     * @return bool
     */
    public function canOversell(): bool
    {
        return $this->canOversell;
    }

    /**
     * Set can oversell
     *
     * @param bool $canOversell
     * @return $this
     */
    public function setCanOversell(bool $canOversell): static
    {
        $this->canOversell = $canOversell;
        return $this;
    }

    /**
     * Check if white label is enabled
     *
     * @return bool
     */
    public function isWhiteLabel(): bool
    {
        return $this->whiteLabel;
    }

    /**
     * Set white label
     *
     * @param bool $whiteLabel
     * @return $this
     */
    public function setWhiteLabel(bool $whiteLabel): static
    {
        $this->whiteLabel = $whiteLabel;
        return $this;
    }

    /**
     * Get nameservers
     *
     * @return array
     */
    public function getNameservers(): array
    {
        return array_filter([
            $this->nameserver1,
            $this->nameserver2,
            $this->nameserver3,
            $this->nameserver4,
        ]);
    }

    /**
     * Set nameservers
     *
     * @param array $nameservers
     * @return $this
     */
    public function setNameservers(array $nameservers): static
    {
        $this->nameserver1 = $nameservers[0] ?? null;
        $this->nameserver2 = $nameservers[1] ?? null;
        $this->nameserver3 = $nameservers[2] ?? null;
        $this->nameserver4 = $nameservers[3] ?? null;
        return $this;
    }

    /**
     * Get allowed features
     *
     * @return array|null
     */
    public function getAllowedFeatures(): ?array
    {
        return $this->allowedFeatures;
    }

    /**
     * Set allowed features
     *
     * @param array|null $allowedFeatures
     * @return $this
     */
    public function setAllowedFeatures(?array $allowedFeatures): static
    {
        $this->allowedFeatures = $allowedFeatures;
        return $this;
    }

    /**
     * Check if feature is allowed
     *
     * @param string $feature
     * @return bool
     */
    public function isFeatureAllowed(string $feature): bool
    {
        if ($this->allowedFeatures === null) {
            return true; // All features allowed if not set
        }

        return in_array($feature, $this->allowedFeatures);
    }

    /**
     * Get user
     *
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Set user
     *
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): static
    {
        $this->user = $user;
        $this->userId = $user->getId();
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
            'user_id' => $this->userId,
            'company_name' => $this->companyName,
            'company_logo' => $this->companyLogo,
            'support_email' => $this->supportEmail,
            'support_phone' => $this->supportPhone,
            'support_url' => $this->supportUrl,
            'max_accounts' => $this->maxAccounts,
            'disk_quota' => $this->diskQuota,
            'bandwidth_quota' => $this->bandwidthQuota,
            'can_create_packages' => $this->canCreatePackages,
            'can_oversell' => $this->canOversell,
            'white_label' => $this->whiteLabel,
            'nameservers' => $this->getNameservers(),
            'allowed_features' => $this->allowedFeatures,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
