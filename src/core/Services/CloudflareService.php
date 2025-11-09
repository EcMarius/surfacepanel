<?php

namespace VirPanel\Core\Services;

/**
 * Cloudflare API Service
 *
 * Handles all interactions with the Cloudflare API
 */
class CloudflareService
{
    private string $apiKey;
    private string $email;
    private string $apiToken;
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';

    /**
     * Create a new Cloudflare service instance
     *
     * @param string $apiKey Legacy API key
     * @param string $email Account email
     * @param string|null $apiToken API token (preferred)
     */
    public function __construct(string $apiKey = '', string $email = '', ?string $apiToken = null)
    {
        $this->apiKey = $apiKey;
        $this->email = $email;
        $this->apiToken = $apiToken ?? '';
    }

    /**
     * Make API request to Cloudflare
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array API response
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
        ];

        // Use API token if available, otherwise use legacy auth
        if ($this->apiToken) {
            $headers[] = 'Authorization: Bearer ' . $this->apiToken;
        } else {
            $headers[] = 'X-Auth-Email: ' . $this->email;
            $headers[] = 'X-Auth-Key: ' . $this->apiKey;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($statusCode >= 400 || !$result['success']) {
            throw new \Exception($result['errors'][0]['message'] ?? 'Cloudflare API error');
        }

        return $result;
    }

    /**
     * List all zones (domains)
     *
     * @return array List of zones
     */
    public function listZones(): array
    {
        $result = $this->request('GET', '/zones');
        return $result['result'] ?? [];
    }

    /**
     * Get zone details by domain name
     *
     * @param string $domain Domain name
     * @return array|null Zone details
     */
    public function getZone(string $domain): ?array
    {
        $result = $this->request('GET', '/zones?name=' . urlencode($domain));
        return $result['result'][0] ?? null;
    }

    /**
     * Add a new zone to Cloudflare
     *
     * @param string $domain Domain name
     * @return array Zone details
     */
    public function addZone(string $domain): array
    {
        $result = $this->request('POST', '/zones', [
            'name' => $domain,
            'jump_start' => true,
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Delete a zone from Cloudflare
     *
     * @param string $zoneId Zone ID
     * @return bool Success status
     */
    public function deleteZone(string $zoneId): bool
    {
        $result = $this->request('DELETE', '/zones/' . $zoneId);
        return $result['success'] ?? false;
    }

    /**
     * List DNS records for a zone
     *
     * @param string $zoneId Zone ID
     * @return array List of DNS records
     */
    public function listDnsRecords(string $zoneId): array
    {
        $result = $this->request('GET', '/zones/' . $zoneId . '/dns_records');
        return $result['result'] ?? [];
    }

    /**
     * Get a specific DNS record
     *
     * @param string $zoneId Zone ID
     * @param string $recordId Record ID
     * @return array|null DNS record details
     */
    public function getDnsRecord(string $zoneId, string $recordId): ?array
    {
        $result = $this->request('GET', '/zones/' . $zoneId . '/dns_records/' . $recordId);
        return $result['result'] ?? null;
    }

    /**
     * Create a DNS record
     *
     * @param string $zoneId Zone ID
     * @param array $data DNS record data
     * @return array Created DNS record
     */
    public function createDnsRecord(string $zoneId, array $data): array
    {
        $result = $this->request('POST', '/zones/' . $zoneId . '/dns_records', $data);
        return $result['result'] ?? [];
    }

    /**
     * Update a DNS record
     *
     * @param string $zoneId Zone ID
     * @param string $recordId Record ID
     * @param array $data DNS record data
     * @return array Updated DNS record
     */
    public function updateDnsRecord(string $zoneId, string $recordId, array $data): array
    {
        $result = $this->request('PUT', '/zones/' . $zoneId . '/dns_records/' . $recordId, $data);
        return $result['result'] ?? [];
    }

    /**
     * Delete a DNS record
     *
     * @param string $zoneId Zone ID
     * @param string $recordId Record ID
     * @return bool Success status
     */
    public function deleteDnsRecord(string $zoneId, string $recordId): bool
    {
        $result = $this->request('DELETE', '/zones/' . $zoneId . '/dns_records/' . $recordId);
        return $result['success'] ?? false;
    }

    /**
     * Purge cache for a zone
     *
     * @param string $zoneId Zone ID
     * @param bool $purgeEverything Whether to purge everything
     * @param array $files Specific files to purge
     * @return bool Success status
     */
    public function purgeCache(string $zoneId, bool $purgeEverything = false, array $files = []): bool
    {
        $data = [];

        if ($purgeEverything) {
            $data['purge_everything'] = true;
        } elseif (!empty($files)) {
            $data['files'] = $files;
        }

        $result = $this->request('POST', '/zones/' . $zoneId . '/purge_cache', $data);
        return $result['success'] ?? false;
    }

    /**
     * Get SSL/TLS settings for a zone
     *
     * @param string $zoneId Zone ID
     * @return array SSL/TLS settings
     */
    public function getSslSettings(string $zoneId): array
    {
        $result = $this->request('GET', '/zones/' . $zoneId . '/settings/ssl');
        return $result['result'] ?? [];
    }

    /**
     * Update SSL/TLS mode for a zone
     *
     * @param string $zoneId Zone ID
     * @param string $mode SSL mode (off, flexible, full, strict)
     * @return array Updated settings
     */
    public function updateSslMode(string $zoneId, string $mode): array
    {
        $result = $this->request('PATCH', '/zones/' . $zoneId . '/settings/ssl', [
            'value' => $mode,
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Get development mode status
     *
     * @param string $zoneId Zone ID
     * @return array Development mode settings
     */
    public function getDevelopmentMode(string $zoneId): array
    {
        $result = $this->request('GET', '/zones/' . $zoneId . '/settings/development_mode');
        return $result['result'] ?? [];
    }

    /**
     * Toggle development mode
     *
     * @param string $zoneId Zone ID
     * @param bool $enabled Enable or disable
     * @return array Updated settings
     */
    public function setDevelopmentMode(string $zoneId, bool $enabled): array
    {
        $result = $this->request('PATCH', '/zones/' . $zoneId . '/settings/development_mode', [
            'value' => $enabled ? 'on' : 'off',
        ]);

        return $result['result'] ?? [];
    }

    /**
     * Get zone analytics
     *
     * @param string $zoneId Zone ID
     * @param int $since Unix timestamp for start time
     * @param int $until Unix timestamp for end time
     * @return array Analytics data
     */
    public function getAnalytics(string $zoneId, int $since = null, int $until = null): array
    {
        $endpoint = '/zones/' . $zoneId . '/analytics/dashboard';

        $params = [];
        if ($since) {
            $params['since'] = $since;
        }
        if ($until) {
            $params['until'] = $until;
        }

        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        $result = $this->request('GET', $endpoint);
        return $result['result'] ?? [];
    }

    /**
     * Verify API credentials
     *
     * @return bool True if credentials are valid
     */
    public function verifyCredentials(): bool
    {
        try {
            $result = $this->request('GET', '/user/tokens/verify');
            return $result['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
