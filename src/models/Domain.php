<?php

namespace VirPanel\Models;

use DateTime;

/**
 * Domain Model
 *
 * Represents domains (main, addon, parked, subdomains)
 */
class Domain
{
    protected ?int $id = null;
    protected int $accountId;
    protected string $domain;
    protected string $type; // main, addon, parked, subdomain
    protected string $documentRoot;
    protected bool $sslEnabled = false;
    protected ?string $sslCertificateId = null;
    protected bool $autosslEnabled = true;
    protected ?DateTime $sslExpiresAt = null;
    protected bool $redirectWww = false;
    protected bool $forceHttps = false;
    protected ?string $redirectUrl = null;
    protected ?int $redirectCode = null; // 301, 302
    protected bool $isActive = true;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    // Relationships
    protected ?Account $account = null;
    protected ?SslCertificate $sslCertificate = null;

    /**
     * Get the domain ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get domain name
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Set domain name
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
     * Get domain type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set domain type
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
     * Check if this is the main domain
     *
     * @return bool
     */
    public function isMainDomain(): bool
    {
        return $this->type === 'main';
    }

    /**
     * Check if this is an addon domain
     *
     * @return bool
     */
    public function isAddonDomain(): bool
    {
        return $this->type === 'addon';
    }

    /**
     * Check if this is a parked domain
     *
     * @return bool
     */
    public function isParkedDomain(): bool
    {
        return $this->type === 'parked';
    }

    /**
     * Check if this is a subdomain
     *
     * @return bool
     */
    public function isSubdomain(): bool
    {
        return $this->type === 'subdomain';
    }

    /**
     * Get document root
     *
     * @return string
     */
    public function getDocumentRoot(): string
    {
        return $this->documentRoot;
    }

    /**
     * Set document root
     *
     * @param string $documentRoot
     * @return $this
     */
    public function setDocumentRoot(string $documentRoot): static
    {
        $this->documentRoot = $documentRoot;
        return $this;
    }

    /**
     * Check if SSL is enabled
     *
     * @return bool
     */
    public function isSslEnabled(): bool
    {
        return $this->sslEnabled;
    }

    /**
     * Enable SSL
     *
     * @param string $certificateId
     * @return $this
     */
    public function enableSsl(string $certificateId): static
    {
        $this->sslEnabled = true;
        $this->sslCertificateId = $certificateId;
        return $this;
    }

    /**
     * Disable SSL
     *
     * @return $this
     */
    public function disableSsl(): static
    {
        $this->sslEnabled = false;
        $this->sslCertificateId = null;
        return $this;
    }

    /**
     * Check if AutoSSL is enabled
     *
     * @return bool
     */
    public function isAutosslEnabled(): bool
    {
        return $this->autosslEnabled;
    }

    /**
     * Set AutoSSL enabled
     *
     * @param bool $autosslEnabled
     * @return $this
     */
    public function setAutosslEnabled(bool $autosslEnabled): static
    {
        $this->autosslEnabled = $autosslEnabled;
        return $this;
    }

    /**
     * Get SSL expiration date
     *
     * @return DateTime|null
     */
    public function getSslExpiresAt(): ?DateTime
    {
        return $this->sslExpiresAt;
    }

    /**
     * Check if SSL certificate is expired
     *
     * @return bool
     */
    public function isSslExpired(): bool
    {
        if (!$this->sslExpiresAt) {
            return false;
        }

        return $this->sslExpiresAt < new DateTime();
    }

    /**
     * Check if SSL certificate is expiring soon (within 30 days)
     *
     * @return bool
     */
    public function isSslExpiringSoon(): bool
    {
        if (!$this->sslExpiresAt) {
            return false;
        }

        $threshold = new DateTime('+30 days');
        return $this->sslExpiresAt < $threshold;
    }

    /**
     * Check if WWW redirect is enabled
     *
     * @return bool
     */
    public function hasRedirectWww(): bool
    {
        return $this->redirectWww;
    }

    /**
     * Set WWW redirect
     *
     * @param bool $redirectWww
     * @return $this
     */
    public function setRedirectWww(bool $redirectWww): static
    {
        $this->redirectWww = $redirectWww;
        return $this;
    }

    /**
     * Check if HTTPS is forced
     *
     * @return bool
     */
    public function hasForceHttps(): bool
    {
        return $this->forceHttps;
    }

    /**
     * Set force HTTPS
     *
     * @param bool $forceHttps
     * @return $this
     */
    public function setForceHttps(bool $forceHttps): static
    {
        $this->forceHttps = $forceHttps;
        return $this;
    }

    /**
     * Get redirect URL
     *
     * @return string|null
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * Set redirect
     *
     * @param string $url
     * @param int $code
     * @return $this
     */
    public function setRedirect(string $url, int $code = 301): static
    {
        $this->redirectUrl = $url;
        $this->redirectCode = $code;
        return $this;
    }

    /**
     * Remove redirect
     *
     * @return $this
     */
    public function removeRedirect(): static
    {
        $this->redirectUrl = null;
        $this->redirectCode = null;
        return $this;
    }

    /**
     * Check if redirect is set
     *
     * @return bool
     */
    public function hasRedirect(): bool
    {
        return !empty($this->redirectUrl);
    }

    /**
     * Check if domain is active
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
            'document_root' => $this->documentRoot,
            'ssl_enabled' => $this->sslEnabled,
            'autossl_enabled' => $this->autosslEnabled,
            'ssl_expires_at' => $this->sslExpiresAt?->format('Y-m-d H:i:s'),
            'redirect_www' => $this->redirectWww,
            'force_https' => $this->forceHttps,
            'redirect_url' => $this->redirectUrl,
            'redirect_code' => $this->redirectCode,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
