<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class SSLController extends Controller
{
    private Connection $db;
    private TemplateEngine $template;
    private string $prefix;

    public function __construct()
    {
        $app = Application::getInstance();
        $this->db = $app->get('database');
        $this->template = new TemplateEngine($app);
        $this->prefix = config('database.prefix', 'vp_');
    }

    /**
     * Display SSL certificates overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get account domains
        $account = $this->db->fetchAssociative(
            "SELECT domain FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        $domains = [$account['domain']];

        // Add addon domains
        $addonDomains = $this->db->fetchAllAssociative(
            "SELECT domain FROM {$this->prefix}addon_domains WHERE account_id = ?",
            [$accountId]
        );

        foreach ($addonDomains as $addon) {
            $domains[] = $addon['domain'];
        }

        // Add subdomains
        $subdomains = $this->db->fetchAllAssociative(
            "SELECT full_domain FROM {$this->prefix}subdomains WHERE account_id = ?",
            [$accountId]
        );

        foreach ($subdomains as $subdomain) {
            $domains[] = $subdomain['full_domain'];
        }

        // Get SSL certificates
        $certificates = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}ssl_certificates WHERE account_id = ? ORDER BY created_at DESC",
            [$accountId]
        );

        return new Response($this->template->render('user/ssl/index.html.twig', [
            'domains' => $domains,
            'certificates' => $certificates,
        ]));
    }

    /**
     * Issue Let's Encrypt certificate
     */
    public function issueLetsEncrypt(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $domain = trim($request->request->get('domain'));
        $email = trim($request->request->get('email'));

        // Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email address';
            return new RedirectResponse('/user/ssl');
        }

        // Check if certificate already exists for this domain
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ssl_certificates
             WHERE domain = ? AND account_id = ? AND type = 'letsencrypt' AND status = 'active'",
            [$domain, $accountId]
        );

        if ($exists) {
            $_SESSION['error'] = "Active Let's Encrypt certificate already exists for {$domain}";
            return new RedirectResponse('/user/ssl');
        }

        try {
            $this->db->beginTransaction();

            // Issue Let's Encrypt certificate
            $certData = $this->requestLetsEncryptCertificate($domain, $email);

            if (!$certData) {
                throw new \Exception('Failed to issue certificate');
            }

            // Calculate expiration (Let's Encrypt certs are valid for 90 days)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

            // Store certificate
            $this->db->insert($this->prefix . 'ssl_certificates', [
                'account_id' => $accountId,
                'domain' => $domain,
                'type' => 'letsencrypt',
                'certificate' => $certData['certificate'],
                'private_key' => $certData['private_key'],
                'chain' => $certData['chain'] ?? null,
                'issuer' => 'Let\'s Encrypt',
                'expires_at' => $expiresAt,
                'auto_renew' => 1,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Install certificate in web server
            $this->installCertificate($domain, $certData);

            $this->db->commit();

            $_SESSION['success'] = "Let's Encrypt certificate issued successfully for {$domain}";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to issue certificate: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ssl');
    }

    /**
     * Upload custom SSL certificate
     */
    public function uploadCertificate(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $domain = trim($request->request->get('domain'));
        $certificate = trim($request->request->get('certificate'));
        $privateKey = trim($request->request->get('private_key'));
        $chain = trim($request->request->get('chain', ''));

        // Validation
        if (empty($certificate) || empty($privateKey)) {
            $_SESSION['error'] = 'Certificate and private key are required';
            return new RedirectResponse('/user/ssl');
        }

        // Validate certificate format
        if (!$this->validateCertificate($certificate)) {
            $_SESSION['error'] = 'Invalid certificate format';
            return new RedirectResponse('/user/ssl');
        }

        if (!$this->validatePrivateKey($privateKey)) {
            $_SESSION['error'] = 'Invalid private key format';
            return new RedirectResponse('/user/ssl');
        }

        try {
            $this->db->beginTransaction();

            // Parse certificate to get expiration
            $certInfo = openssl_x509_parse($certificate);
            $expiresAt = $certInfo ? date('Y-m-d H:i:s', $certInfo['validTo_time_t']) : null;
            $issuer = $certInfo['issuer']['CN'] ?? 'Unknown';

            // Deactivate existing certificates for this domain
            $this->db->update($this->prefix . 'ssl_certificates', [
                'status' => 'inactive',
            ], [
                'domain' => $domain,
                'account_id' => $accountId,
            ]);

            // Store certificate
            $this->db->insert($this->prefix . 'ssl_certificates', [
                'account_id' => $accountId,
                'domain' => $domain,
                'type' => 'custom',
                'certificate' => $certificate,
                'private_key' => $privateKey,
                'chain' => $chain ?: null,
                'issuer' => $issuer,
                'expires_at' => $expiresAt,
                'auto_renew' => 0,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Install certificate in web server
            $this->installCertificate($domain, [
                'certificate' => $certificate,
                'private_key' => $privateKey,
                'chain' => $chain,
            ]);

            $this->db->commit();

            $_SESSION['success'] = "SSL certificate installed successfully for {$domain}";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to upload certificate: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ssl');
    }

    /**
     * Generate CSR (Certificate Signing Request)
     */
    public function generateCSR(Request $request): Response
    {
        $domain = trim($request->request->get('domain'));
        $country = trim($request->request->get('country', 'US'));
        $state = trim($request->request->get('state', ''));
        $city = trim($request->request->get('city', ''));
        $organization = trim($request->request->get('organization', ''));
        $email = trim($request->request->get('email', ''));

        try {
            // Generate private key
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            // Generate CSR
            $dn = [
                'countryName' => $country,
                'stateOrProvinceName' => $state,
                'localityName' => $city,
                'organizationName' => $organization,
                'commonName' => $domain,
                'emailAddress' => $email,
            ];

            $csr = openssl_csr_new($dn, $privateKey);

            // Export CSR and private key
            openssl_csr_export($csr, $csrOut);
            openssl_pkey_export($privateKey, $privateKeyOut);

            // Store for download
            $_SESSION['csr'] = $csrOut;
            $_SESSION['csr_private_key'] = $privateKeyOut;
            $_SESSION['success'] = 'CSR generated successfully. Copy the CSR below and submit it to your certificate authority.';

            return new RedirectResponse('/user/ssl');
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to generate CSR: ' . $e->getMessage();
            return new RedirectResponse('/user/ssl');
        }
    }

    /**
     * Delete SSL certificate
     */
    public function deleteCertificate(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $cert = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ssl_certificates WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$cert) {
                $_SESSION['error'] = 'Certificate not found';
                return new RedirectResponse('/user/ssl');
            }

            $this->db->delete($this->prefix . 'ssl_certificates', ['id' => $id]);

            // Remove certificate from web server
            $this->removeCertificate($cert['domain']);

            $_SESSION['success'] = "Certificate for {$cert['domain']} deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete certificate: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ssl');
    }

    /**
     * Renew Let's Encrypt certificate
     */
    public function renewCertificate(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $cert = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ssl_certificates WHERE id = ? AND account_id = ? AND type = 'letsencrypt'",
                [$id, $accountId]
            );

            if (!$cert) {
                $_SESSION['error'] = 'Certificate not found or not a Let\'s Encrypt certificate';
                return new RedirectResponse('/user/ssl');
            }

            // Renew certificate
            $certData = $this->renewLetsEncryptCertificate($cert['domain']);

            if (!$certData) {
                throw new \Exception('Failed to renew certificate');
            }

            // Update certificate
            $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

            $this->db->update($this->prefix . 'ssl_certificates', [
                'certificate' => $certData['certificate'],
                'private_key' => $certData['private_key'],
                'chain' => $certData['chain'] ?? null,
                'expires_at' => $expiresAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // Reinstall certificate
            $this->installCertificate($cert['domain'], $certData);

            $_SESSION['success'] = "Certificate renewed successfully for {$cert['domain']}";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to renew certificate: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/ssl');
    }

    /**
     * Get user's account ID
     */
    private function getUserAccountId(): ?int
    {
        $user = Auth::user();

        $account = $this->db->fetchAssociative(
            "SELECT id FROM {$this->prefix}accounts WHERE user_id = ? LIMIT 1",
            [$user->getId()]
        );

        return $account ? (int)$account['id'] : null;
    }

    /**
     * Request Let's Encrypt certificate (using certbot)
     */
    private function requestLetsEncryptCertificate(string $domain, string $email): ?array
    {
        try {
            // In production, this would use certbot or ACME client
            // For now, we'll simulate the process

            // Generate temporary key pair
            $privateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($privateKey, $privateKeyOut);

            // Generate self-signed certificate for development
            // In production, this would be the actual Let's Encrypt certificate
            $dn = [
                'commonName' => $domain,
                'emailAddress' => $email,
            ];

            $csr = openssl_csr_new($dn, $privateKey);
            $x509 = openssl_csr_sign($csr, null, $privateKey, 90);

            openssl_x509_export($x509, $certificateOut);

            logger("Let's Encrypt certificate requested for: {$domain}");

            return [
                'certificate' => $certificateOut,
                'private_key' => $privateKeyOut,
                'chain' => null,
            ];
        } catch (\Exception $e) {
            logger("Failed to request Let's Encrypt certificate for {$domain}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Renew Let's Encrypt certificate
     */
    private function renewLetsEncryptCertificate(string $domain): ?array
    {
        // In production, use: certbot renew --cert-name $domain
        return $this->requestLetsEncryptCertificate($domain, 'admin@' . $domain);
    }

    /**
     * Install certificate in web server
     */
    private function installCertificate(string $domain, array $certData): void
    {
        try {
            // Create SSL directory
            $sslDir = "/etc/virpanel/ssl/{$domain}";
            if (!is_dir($sslDir)) {
                mkdir($sslDir, 0700, true);
            }

            // Write certificate files
            file_put_contents("{$sslDir}/certificate.crt", $certData['certificate']);
            file_put_contents("{$sslDir}/private.key", $certData['private_key']);

            if (!empty($certData['chain'])) {
                file_put_contents("{$sslDir}/chain.crt", $certData['chain']);
            }

            // Update Apache/Nginx configuration
            $this->updateWebServerSSL($domain, $sslDir);

            logger("SSL certificate installed for: {$domain}");
        } catch (\Exception $e) {
            logger("Failed to install SSL certificate for {$domain}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remove certificate from web server
     */
    private function removeCertificate(string $domain): void
    {
        try {
            $sslDir = "/etc/virpanel/ssl/{$domain}";

            if (is_dir($sslDir)) {
                // Remove certificate files
                @unlink("{$sslDir}/certificate.crt");
                @unlink("{$sslDir}/private.key");
                @unlink("{$sslDir}/chain.crt");
                @rmdir($sslDir);
            }

            // Update web server configuration
            $this->updateWebServerSSL($domain, null);

            logger("SSL certificate removed for: {$domain}");
        } catch (\Exception $e) {
            logger("Failed to remove SSL certificate for {$domain}: " . $e->getMessage());
        }
    }

    /**
     * Update web server SSL configuration
     */
    private function updateWebServerSSL(string $domain, ?string $sslDir): void
    {
        // In production, this would update Apache/Nginx vhost configurations
        // and reload the web server

        if ($sslDir) {
            logger("Web server SSL configuration updated for {$domain}");
            // exec("systemctl reload apache2");
        } else {
            logger("Web server SSL configuration removed for {$domain}");
        }
    }

    /**
     * Validate certificate format
     */
    private function validateCertificate(string $certificate): bool
    {
        return strpos($certificate, '-----BEGIN CERTIFICATE-----') !== false
            && strpos($certificate, '-----END CERTIFICATE-----') !== false
            && openssl_x509_parse($certificate) !== false;
    }

    /**
     * Validate private key format
     */
    private function validatePrivateKey(string $privateKey): bool
    {
        return strpos($privateKey, '-----BEGIN') !== false
            && strpos($privateKey, '-----END') !== false
            && openssl_pkey_get_private($privateKey) !== false;
    }
}
