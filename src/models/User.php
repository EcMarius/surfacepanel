<?php

namespace VirPanel\Models;

use DateTime;

/**
 * User Model
 *
 * Represents WHM admin users and resellers
 */
class User
{
    protected ?int $id = null;
    protected string $username;
    protected string $email;
    protected string $password;
    protected ?string $fullName = null;
    protected string $role = 'admin'; // root, admin, reseller
    protected bool $isActive = true;
    protected bool $twoFactorEnabled = false;
    protected ?string $twoFactorSecret = null;
    protected ?DateTime $lastLoginAt = null;
    protected ?string $lastLoginIp = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;
    protected ?DateTime $deletedAt = null;

    // Relationships
    protected array $accounts = [];
    protected ?ResellerSettings $resellerSettings = null;

    /**
     * Get the user ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the username
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Set the username
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
     * Get the email
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set the email
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
     * Get the password
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Set the password
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
     * Get the full name
     *
     * @return string|null
     */
    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    /**
     * Set the full name
     *
     * @param string|null $fullName
     * @return $this
     */
    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    /**
     * Get the role
     *
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Set the role
     *
     * @param string $role
     * @return $this
     */
    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    /**
     * Check if user is root
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->role === 'root';
    }

    /**
     * Check if user is reseller
     *
     * @return bool
     */
    public function isReseller(): bool
    {
        return $this->role === 'reseller';
    }

    /**
     * Check if user is active
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
     * Check if two-factor is enabled
     *
     * @return bool
     */
    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    /**
     * Enable two-factor authentication
     *
     * @param string $secret
     * @return $this
     */
    public function enableTwoFactor(string $secret): static
    {
        $this->twoFactorEnabled = true;
        $this->twoFactorSecret = $secret;
        return $this;
    }

    /**
     * Disable two-factor authentication
     *
     * @return $this
     */
    public function disableTwoFactor(): static
    {
        $this->twoFactorEnabled = false;
        $this->twoFactorSecret = null;
        return $this;
    }

    /**
     * Get accounts
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
     * Get reseller settings
     *
     * @return ResellerSettings|null
     */
    public function getResellerSettings(): ?ResellerSettings
    {
        return $this->resellerSettings;
    }

    /**
     * Set reseller settings
     *
     * @param ResellerSettings $settings
     * @return $this
     */
    public function setResellerSettings(ResellerSettings $settings): static
    {
        $this->resellerSettings = $settings;
        return $this;
    }

    /**
     * Get created timestamp
     *
     * @return DateTime|null
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get updated timestamp
     *
     * @return DateTime|null
     */
    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
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
            'email' => $this->email,
            'full_name' => $this->fullName,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'two_factor_enabled' => $this->twoFactorEnabled,
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->lastLoginIp,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
