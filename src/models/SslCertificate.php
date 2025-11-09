<?php

namespace VirPanel\Models;

use DateTime;

/**
 * SslCertificate Model
 *
 * Represents SSL/TLS certificates
 */
class SslCertificate
{
    protected ?int $id = null;
    protected int $accountId;
    protected string $domain;
    protected string $type; // self_signed, lets_encrypt, commercial, custom
    protected string $certificate;
    protected string $privateKey;
    protected ?string $chain = null;
    protected ?string $issuer = null;
    protected ?DateTime $issuedAt = null;
    protected DateTime $expiresAt;
    protected bool $autoRenew = true;
    protected bool $isActive = true;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    // Relationships
    protected ?Account $account = null;

    /**
     * Get the certificate ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
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
     * Get certificate type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set certificate type
     *
     * @param string $type
     * @return $this
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Check if this is a Let's Encrypt certificate
     *
     * @return bool
     */
    public function isLetsEncrypt(): bool
    {
        return $this->type === 'lets_encrypt';
    }

    /**
     * Get certificate
     *
     * @return string
     */
    public function getCertificate(): string
    {
        return $this->certificate;
    }

    /**
     * Set certificate
     *
     * @param string $certificate
     * @return $this
     */
    public function setCertificate(string $certificate): static
    {
        $this->certificate = $certificate;
        return $this;
    }

    /**
     * Get private key
     *
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    /**
     * Set private key
     *
     * @param string $privateKey
     * @return $this
     */
    public function setPrivateKey(string $privateKey): static
    {
        $this->privateKey = $privateKey;
        return $this;
    }

    /**
     * Get certificate chain
     *
     * @return string|null
     */
    public function getChain(): ?string
    {
        return $this->chain;
    }

    /**
     * Set certificate chain
     *
     * @param string|null $chain
     * @return $this
     */
    public function setChain(?string $chain): static
    {
        $this->chain = $chain;
        return $this;
    }

    /**
     * Get issuer
     *
     * @return string|null
     */
    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    /**
     * Set issuer
     *
     * @param string|null $issuer
     * @return $this
     */
    public function setIssuer(?string $issuer): static
    {
        $this->issuer = $issuer;
        return $this;
    }

    /**
     * Get expiration date
     *
     * @return DateTime
     */
    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Set expiration date
     *
     * @param DateTime $expiresAt
     * @return $this
     */
    public function setExpiresAt(DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Check if certificate is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTime();
    }

    /**
     * Check if certificate is expiring soon (within 30 days)
     *
     * @return bool
     */
    public function isExpiringSoon(): bool
    {
        $threshold = new DateTime('+30 days');
        return $this->expiresAt < $threshold;
    }

    /**
     * Get days until expiration
     *
     * @return int
     */
    public function getDaysUntilExpiration(): int
    {
        $now = new DateTime();
        $diff = $now->diff($this->expiresAt);
        return $diff->days * ($diff->invert ? -1 : 1);
    }

    /**
     * Check if auto-renew is enabled
     *
     * @return bool
     */
    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    /**
     * Set auto-renew
     *
     * @param bool $autoRenew
     * @return $this
     */
    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;
        return $this;
    }

    /**
     * Check if certificate is active
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
     * Get account
     *
     * @return Account|null
     */
    public function getAccount(): ?Account
    {
        return $this->account;
    }

    /**
     * Set account
     *
     * @param Account $account
     * @return $this
     */
    public function setAccount(Account $account): static
    {
        $this->account = $account;
        $this->accountId = $account->getId();
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
            'account_id' => $this->accountId,
            'domain' => $this->domain,
            'type' => $this->type,
            'issuer' => $this->issuer,
            'issued_at' => $this->issuedAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'auto_renew' => $this->autoRenew,
            'is_active' => $this->isActive,
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
