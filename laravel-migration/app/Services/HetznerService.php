<?php

namespace App\Services;

use App\Models\HetznerConfig;
use App\Models\HetznerServer;
use App\Models\HetznerVolume;
use App\Models\HetznerSnapshot;
use App\Models\HetznerBilling;
use App\Models\HetznerFloatingIp;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class HetznerService
{
    private const API_BASE_URL = 'https://api.hetzner.cloud/v1';

    private Client $client;
    private ?string $apiToken = null;

    public function __construct()
    {
        $config = HetznerConfig::getInstance();
        $this->apiToken = $config->decrypted_token;

        $this->client = new Client([
            'base_uri' => self::API_BASE_URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Make API request
     */
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        if (!$this->apiToken) {
            Log::error('Hetzner API token not configured');
            return null;
        }

        try {
            $options = [];

            if ($method === 'GET' && !empty($data)) {
                $options['query'] = $data;
            } elseif (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $body = json_decode($response->getBody()->getContents(), true);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Hetzner API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all servers from Hetzner
     */
    public function getServers(): ?array
    {
        $result = $this->request('GET', '/servers');
        return $result['servers'] ?? null;
    }

    /**
     * Get server details
     */
    public function getServer(int $serverId): ?array
    {
        $result = $this->request('GET', "/servers/{$serverId}");
        return $result['server'] ?? null;
    }

    /**
     * Create a new server
     */
    public function createServer(array $data): ?array
    {
        $result = $this->request('POST', '/servers', $data);
        return $result;
    }

    /**
     * Delete a server
     */
    public function deleteServer(int $serverId): bool
    {
        $result = $this->request('DELETE', "/servers/{$serverId}");
        return $result !== null;
    }

    /**
     * Power on a server
     */
    public function powerOnServer(int $serverId): ?array
    {
        $result = $this->request('POST', "/servers/{$serverId}/actions/poweron");
        return $result;
    }

    /**
     * Power off a server
     */
    public function powerOffServer(int $serverId): ?array
    {
        $result = $this->request('POST', "/servers/{$serverId}/actions/poweroff");
        return $result;
    }

    /**
     * Reboot a server
     */
    public function rebootServer(int $serverId): ?array
    {
        $result = $this->request('POST', "/servers/{$serverId}/actions/reboot");
        return $result;
    }

    /**
     * Reset a server
     */
    public function resetServer(int $serverId): ?array
    {
        $result = $this->request('POST', "/servers/{$serverId}/actions/reset");
        return $result;
    }

    /**
     * Get all volumes
     */
    public function getVolumes(): ?array
    {
        $result = $this->request('GET', '/volumes');
        return $result['volumes'] ?? null;
    }

    /**
     * Get volume details
     */
    public function getVolume(int $volumeId): ?array
    {
        $result = $this->request('GET', "/volumes/{$volumeId}");
        return $result['volume'] ?? null;
    }

    /**
     * Create a volume
     */
    public function createVolume(array $data): ?array
    {
        $result = $this->request('POST', '/volumes', $data);
        return $result;
    }

    /**
     * Delete a volume
     */
    public function deleteVolume(int $volumeId): bool
    {
        $result = $this->request('DELETE', "/volumes/{$volumeId}");
        return $result !== null;
    }

    /**
     * Attach volume to server
     */
    public function attachVolume(int $volumeId, int $serverId, ?bool $automount = false): ?array
    {
        $result = $this->request('POST', "/volumes/{$volumeId}/actions/attach", [
            'server' => $serverId,
            'automount' => $automount,
        ]);
        return $result;
    }

    /**
     * Detach volume from server
     */
    public function detachVolume(int $volumeId): ?array
    {
        $result = $this->request('POST', "/volumes/{$volumeId}/actions/detach");
        return $result;
    }

    /**
     * Resize volume
     */
    public function resizeVolume(int $volumeId, int $size): ?array
    {
        $result = $this->request('POST', "/volumes/{$volumeId}/actions/resize", [
            'size' => $size,
        ]);
        return $result;
    }

    /**
     * Get all snapshots
     */
    public function getSnapshots(?string $type = null): ?array
    {
        $params = $type ? ['type' => $type] : [];
        $result = $this->request('GET', '/images', $params);
        return $result['images'] ?? null;
    }

    /**
     * Create server snapshot
     */
    public function createServerSnapshot(int $serverId, string $description = ''): ?array
    {
        $result = $this->request('POST', "/servers/{$serverId}/actions/create_image", [
            'description' => $description,
            'type' => 'snapshot',
        ]);
        return $result;
    }

    /**
     * Delete snapshot
     */
    public function deleteSnapshot(int $snapshotId): bool
    {
        $result = $this->request('DELETE', "/images/{$snapshotId}");
        return $result !== null;
    }

    /**
     * Get all floating IPs
     */
    public function getFloatingIps(): ?array
    {
        $result = $this->request('GET', '/floating_ips');
        return $result['floating_ips'] ?? null;
    }

    /**
     * Create floating IP
     */
    public function createFloatingIp(array $data): ?array
    {
        $result = $this->request('POST', '/floating_ips', $data);
        return $result;
    }

    /**
     * Delete floating IP
     */
    public function deleteFloatingIp(int $floatingIpId): bool
    {
        $result = $this->request('DELETE', "/floating_ips/{$floatingIpId}");
        return $result !== null;
    }

    /**
     * Assign floating IP to server
     */
    public function assignFloatingIp(int $floatingIpId, int $serverId): ?array
    {
        $result = $this->request('POST', "/floating_ips/{$floatingIpId}/actions/assign", [
            'server' => $serverId,
        ]);
        return $result;
    }

    /**
     * Unassign floating IP
     */
    public function unassignFloatingIp(int $floatingIpId): ?array
    {
        $result = $this->request('POST', "/floating_ips/{$floatingIpId}/actions/unassign");
        return $result;
    }

    /**
     * Get all SSH keys
     */
    public function getSshKeys(): ?array
    {
        $result = $this->request('GET', '/ssh_keys');
        return $result['ssh_keys'] ?? null;
    }

    /**
     * Create SSH key
     */
    public function createSshKey(string $name, string $publicKey): ?array
    {
        $result = $this->request('POST', '/ssh_keys', [
            'name' => $name,
            'public_key' => $publicKey,
        ]);
        return $result;
    }

    /**
     * Delete SSH key
     */
    public function deleteSshKey(int $sshKeyId): bool
    {
        $result = $this->request('DELETE', "/ssh_keys/{$sshKeyId}");
        return $result !== null;
    }

    /**
     * Get all firewalls
     */
    public function getFirewalls(): ?array
    {
        $result = $this->request('GET', '/firewalls');
        return $result['firewalls'] ?? null;
    }

    /**
     * Create firewall
     */
    public function createFirewall(array $data): ?array
    {
        $result = $this->request('POST', '/firewalls', $data);
        return $result;
    }

    /**
     * Delete firewall
     */
    public function deleteFirewall(int $firewallId): bool
    {
        $result = $this->request('DELETE', "/firewalls/{$firewallId}");
        return $result !== null;
    }

    /**
     * Apply firewall to resources
     */
    public function applyFirewall(int $firewallId, array $resources): ?array
    {
        $result = $this->request('POST', "/firewalls/{$firewallId}/actions/apply_to_resources", [
            'apply_to' => $resources,
        ]);
        return $result;
    }

    /**
     * Get all networks
     */
    public function getNetworks(): ?array
    {
        $result = $this->request('GET', '/networks');
        return $result['networks'] ?? null;
    }

    /**
     * Create network
     */
    public function createNetwork(array $data): ?array
    {
        $result = $this->request('POST', '/networks', $data);
        return $result;
    }

    /**
     * Delete network
     */
    public function deleteNetwork(int $networkId): bool
    {
        $result = $this->request('DELETE', "/networks/{$networkId}");
        return $result !== null;
    }

    /**
     * Get all load balancers
     */
    public function getLoadBalancers(): ?array
    {
        $result = $this->request('GET', '/load_balancers');
        return $result['load_balancers'] ?? null;
    }

    /**
     * Create load balancer
     */
    public function createLoadBalancer(array $data): ?array
    {
        $result = $this->request('POST', '/load_balancers', $data);
        return $result;
    }

    /**
     * Delete load balancer
     */
    public function deleteLoadBalancer(int $loadBalancerId): bool
    {
        $result = $this->request('DELETE', "/load_balancers/{$loadBalancerId}");
        return $result !== null;
    }

    /**
     * Get server types
     */
    public function getServerTypes(): ?array
    {
        $result = $this->request('GET', '/server_types');
        return $result['server_types'] ?? null;
    }

    /**
     * Get images
     */
    public function getImages(?string $type = null): ?array
    {
        $params = $type ? ['type' => $type] : [];
        $result = $this->request('GET', '/images', $params);
        return $result['images'] ?? null;
    }

    /**
     * Get datacenters
     */
    public function getDatacenters(): ?array
    {
        $result = $this->request('GET', '/datacenters');
        return $result['datacenters'] ?? null;
    }

    /**
     * Get locations
     */
    public function getLocations(): ?array
    {
        $result = $this->request('GET', '/locations');
        return $result['locations'] ?? null;
    }

    /**
     * Sync servers from Hetzner to database
     */
    public function syncServers(): int
    {
        $servers = $this->getServers();
        if (!$servers) {
            return 0;
        }

        $syncedCount = 0;

        foreach ($servers as $serverData) {
            $server = HetznerServer::updateOrCreate(
                ['hetzner_server_id' => $serverData['id']],
                [
                    'name' => $serverData['name'],
                    'server_type' => $serverData['server_type']['name'] ?? 'unknown',
                    'datacenter' => $serverData['datacenter']['name'] ?? '',
                    'location' => $serverData['datacenter']['location']['name'] ?? '',
                    'image' => $serverData['image']['name'] ?? '',
                    'status' => $serverData['status'],
                    'public_ipv4' => $serverData['public_net']['ipv4']['ip'] ?? null,
                    'public_ipv6' => $serverData['public_net']['ipv6']['ip'] ?? null,
                    'disk_size' => $serverData['server_type']['disk'] ?? 0,
                    'vcpus' => $serverData['server_type']['cores'] ?? 0,
                    'memory' => $serverData['server_type']['memory'] * 1024 ?? 0, // Convert to MB
                    'hourly_price' => $serverData['server_type']['prices'][0]['price_hourly']['gross'] ?? 0,
                    'monthly_price' => $serverData['server_type']['prices'][0]['price_monthly']['gross'] ?? 0,
                    'backups_enabled' => $serverData['backup_window'] !== null,
                    'labels' => $serverData['labels'] ?? [],
                    'created_at_hetzner' => $serverData['created'],
                ]
            );

            $syncedCount++;
        }

        return $syncedCount;
    }

    /**
     * Sync volumes from Hetzner to database
     */
    public function syncVolumes(): int
    {
        $volumes = $this->getVolumes();
        if (!$volumes) {
            return 0;
        }

        $syncedCount = 0;

        foreach ($volumes as $volumeData) {
            $serverId = null;
            if ($volumeData['server']) {
                $server = HetznerServer::where('hetzner_server_id', $volumeData['server'])->first();
                $serverId = $server?->id;
            }

            HetznerVolume::updateOrCreate(
                ['hetzner_volume_id' => $volumeData['id']],
                [
                    'server_id' => $serverId,
                    'name' => $volumeData['name'],
                    'size' => $volumeData['size'],
                    'location' => $volumeData['location']['name'] ?? '',
                    'format' => $volumeData['format'] ?? null,
                    'linux_device' => $volumeData['linux_device'] ?? null,
                    'status' => $volumeData['status'],
                    'monthly_price' => $volumeData['protection']['delete'] ? 0 : ($volumeData['size'] * 0.045), // Approximate
                    'labels' => $volumeData['labels'] ?? [],
                ]
            );

            $syncedCount++;
        }

        return $syncedCount;
    }
}
