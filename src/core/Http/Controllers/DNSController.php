<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class DNSController extends Controller
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
     * Display DNS zones overview
     */
    public function index(Request $request): Response
    {
        $accountId = $this->getUserAccountId();

        if (!$accountId) {
            $_SESSION['error'] = 'No account found';
            return new RedirectResponse('/user/dashboard');
        }

        // Get all domains for this account
        $account = $this->db->fetchAssociative(
            "SELECT domain FROM {$this->prefix}accounts WHERE id = ?",
            [$accountId]
        );

        $domains = [$account['domain']];

        // Get addon domains
        $addonDomains = $this->db->fetchAllAssociative(
            "SELECT domain FROM {$this->prefix}addon_domains WHERE account_id = ?",
            [$accountId]
        );

        foreach ($addonDomains as $addon) {
            $domains[] = $addon['domain'];
        }

        // Get DNS zones
        $zones = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}dns_zones WHERE account_id = ? ORDER BY domain ASC",
            [$accountId]
        );

        // Get record counts for each zone
        foreach ($zones as &$zone) {
            $zone['record_count'] = (int)$this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}dns_records WHERE zone_id = ?",
                [$zone['id']]
            );
        }

        return new Response($this->template->render('user/dns/index.html.twig', [
            'zones' => $zones,
            'domains' => $domains,
        ]));
    }

    /**
     * Create new DNS zone
     */
    public function createZone(Request $request): Response
    {
        $accountId = $this->getUserAccountId();
        $domain = strtolower(trim($request->request->get('domain')));

        // Validation
        if (!$domain) {
            $_SESSION['error'] = 'Domain is required';
            return new RedirectResponse('/user/dns');
        }

        // Check if zone already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}dns_zones WHERE domain = ? AND account_id = ?",
            [$domain, $accountId]
        );

        if ($exists) {
            $_SESSION['error'] = "DNS zone for {$domain} already exists";
            return new RedirectResponse('/user/dns');
        }

        try {
            $this->db->beginTransaction();

            // Get account details
            $account = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            // Get primary nameserver from config or use default
            $ns1 = config('dns.nameserver1', 'ns1.virpanel.com');
            $ns2 = config('dns.nameserver2', 'ns2.virpanel.com');

            // Generate SOA record
            $serial = date('Ymd') . '01'; // YYYYMMDDNN format
            $soa = "{$ns1}. hostmaster.{$domain}. {$serial} 3600 1800 1209600 86400";

            // Create DNS zone
            $this->db->insert($this->prefix . 'dns_zones', [
                'account_id' => $accountId,
                'domain' => $domain,
                'soa' => $soa,
                'serial' => $serial,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $zoneId = $this->db->lastInsertId();

            // Get server IP (or use account's IP if dedicated)
            $serverIp = config('server.ip', '127.0.0.1');

            // Create default DNS records
            $defaultRecords = [
                // SOA record
                ['name' => '@', 'type' => 'SOA', 'content' => $soa, 'ttl' => 86400, 'priority' => 0],

                // NS records
                ['name' => '@', 'type' => 'NS', 'content' => $ns1 . '.', 'ttl' => 86400, 'priority' => 0],
                ['name' => '@', 'type' => 'NS', 'content' => $ns2 . '.', 'ttl' => 86400, 'priority' => 0],

                // A record for domain
                ['name' => '@', 'type' => 'A', 'content' => $serverIp, 'ttl' => 14400, 'priority' => 0],

                // A record for www
                ['name' => 'www', 'type' => 'A', 'content' => $serverIp, 'ttl' => 14400, 'priority' => 0],

                // MX record
                ['name' => '@', 'type' => 'MX', 'content' => "mail.{$domain}.", 'ttl' => 14400, 'priority' => 10],

                // A record for mail
                ['name' => 'mail', 'type' => 'A', 'content' => $serverIp, 'ttl' => 14400, 'priority' => 0],

                // TXT record for SPF
                ['name' => '@', 'type' => 'TXT', 'content' => '"v=spf1 a mx ip4:' . $serverIp . ' ~all"', 'ttl' => 14400, 'priority' => 0],
            ];

            foreach ($defaultRecords as $record) {
                $this->db->insert($this->prefix . 'dns_records', [
                    'zone_id' => $zoneId,
                    'name' => $record['name'],
                    'type' => $record['type'],
                    'content' => $record['content'],
                    'ttl' => $record['ttl'],
                    'priority' => $record['priority'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Write zone file
            $this->writeZoneFile($zoneId);

            // Reload DNS server
            $this->reloadDNSServer();

            $this->db->commit();

            $_SESSION['success'] = "DNS zone for {$domain} created successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to create DNS zone: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/dns');
    }

    /**
     * View DNS zone records
     */
    public function viewZone(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        $zone = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}dns_zones WHERE id = ? AND account_id = ?",
            [$id, $accountId]
        );

        if (!$zone) {
            $_SESSION['error'] = 'DNS zone not found';
            return new RedirectResponse('/user/dns');
        }

        // Get all records for this zone
        $records = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}dns_records WHERE zone_id = ? ORDER BY type, name",
            [$id]
        );

        return new Response($this->template->render('user/dns/view.html.twig', [
            'zone' => $zone,
            'records' => $records,
        ]));
    }

    /**
     * Add DNS record
     */
    public function addRecord(Request $request, int $zoneId): Response
    {
        $accountId = $this->getUserAccountId();

        $name = trim($request->request->get('name'));
        $type = strtoupper(trim($request->request->get('type')));
        $content = trim($request->request->get('content'));
        $ttl = (int)$request->request->get('ttl', 14400);
        $priority = (int)$request->request->get('priority', 0);

        // Validate zone ownership
        $zone = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}dns_zones WHERE id = ? AND account_id = ?",
            [$zoneId, $accountId]
        );

        if (!$zone) {
            $_SESSION['error'] = 'DNS zone not found';
            return new RedirectResponse('/user/dns');
        }

        // Validation
        if (!$name || !$type || !$content) {
            $_SESSION['error'] = 'Name, type, and content are required';
            return new RedirectResponse("/user/dns/zone/{$zoneId}");
        }

        // Validate record type
        $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];
        if (!in_array($type, $validTypes)) {
            $_SESSION['error'] = 'Invalid record type';
            return new RedirectResponse("/user/dns/zone/{$zoneId}");
        }

        // Validate content based on type
        if (!$this->validateRecordContent($type, $content)) {
            $_SESSION['error'] = "Invalid content format for {$type} record";
            return new RedirectResponse("/user/dns/zone/{$zoneId}");
        }

        try {
            $this->db->beginTransaction();

            // Add record
            $this->db->insert($this->prefix . 'dns_records', [
                'zone_id' => $zoneId,
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'ttl' => $ttl,
                'priority' => $priority,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update zone serial
            $this->updateZoneSerial($zoneId);

            // Write zone file
            $this->writeZoneFile($zoneId);

            // Reload DNS server
            $this->reloadDNSServer();

            $this->db->commit();

            $_SESSION['success'] = 'DNS record added successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to add DNS record: ' . $e->getMessage();
        }

        return new RedirectResponse("/user/dns/zone/{$zoneId}");
    }

    /**
     * Delete DNS record
     */
    public function deleteRecord(Request $request, int $zoneId, int $recordId): Response
    {
        $accountId = $this->getUserAccountId();

        // Validate zone ownership
        $zone = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}dns_zones WHERE id = ? AND account_id = ?",
            [$zoneId, $accountId]
        );

        if (!$zone) {
            $_SESSION['error'] = 'DNS zone not found';
            return new RedirectResponse('/user/dns');
        }

        try {
            $this->db->beginTransaction();

            // Don't allow deletion of SOA record
            $record = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}dns_records WHERE id = ? AND zone_id = ?",
                [$recordId, $zoneId]
            );

            if ($record && $record['type'] === 'SOA') {
                $_SESSION['error'] = 'Cannot delete SOA record';
                return new RedirectResponse("/user/dns/zone/{$zoneId}");
            }

            // Delete record
            $this->db->delete($this->prefix . 'dns_records', [
                'id' => $recordId,
                'zone_id' => $zoneId,
            ]);

            // Update zone serial
            $this->updateZoneSerial($zoneId);

            // Write zone file
            $this->writeZoneFile($zoneId);

            // Reload DNS server
            $this->reloadDNSServer();

            $this->db->commit();

            $_SESSION['success'] = 'DNS record deleted successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete DNS record: ' . $e->getMessage();
        }

        return new RedirectResponse("/user/dns/zone/{$zoneId}");
    }

    /**
     * Delete DNS zone
     */
    public function deleteZone(Request $request, int $id): Response
    {
        $accountId = $this->getUserAccountId();

        try {
            $this->db->beginTransaction();

            // Validate zone ownership
            $zone = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}dns_zones WHERE id = ? AND account_id = ?",
                [$id, $accountId]
            );

            if (!$zone) {
                $_SESSION['error'] = 'DNS zone not found';
                return new RedirectResponse('/user/dns');
            }

            // Delete all records
            $this->db->delete($this->prefix . 'dns_records', ['zone_id' => $id]);

            // Delete zone
            $this->db->delete($this->prefix . 'dns_zones', ['id' => $id]);

            // Remove zone file
            $this->removeZoneFile($zone['domain']);

            // Reload DNS server
            $this->reloadDNSServer();

            $this->db->commit();

            $_SESSION['success'] = "DNS zone for {$zone['domain']} deleted successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to delete DNS zone: ' . $e->getMessage();
        }

        return new RedirectResponse('/user/dns');
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
     * Validate DNS record content based on type
     */
    private function validateRecordContent(string $type, string $content): bool
    {
        switch ($type) {
            case 'A':
                return filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

            case 'AAAA':
                return filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

            case 'CNAME':
            case 'NS':
            case 'MX':
                // Should end with a dot for FQDN
                return !empty($content);

            case 'TXT':
                // TXT records should be quoted
                return !empty($content);

            default:
                return !empty($content);
        }
    }

    /**
     * Update zone serial number
     */
    private function updateZoneSerial(int $zoneId): void
    {
        $zone = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}dns_zones WHERE id = ?",
            [$zoneId]
        );

        $currentSerial = $zone['serial'];
        $today = date('Ymd');

        // If serial starts with today's date, increment the counter
        if (substr($currentSerial, 0, 8) === $today) {
            $counter = (int)substr($currentSerial, 8) + 1;
            $newSerial = $today . str_pad($counter, 2, '0', STR_PAD_LEFT);
        } else {
            // New day, reset to 01
            $newSerial = $today . '01';
        }

        // Update zone serial
        $this->db->update($this->prefix . 'dns_zones', [
            'serial' => $newSerial,
        ], ['id' => $zoneId]);

        // Update SOA record
        $soaRecord = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}dns_records WHERE zone_id = ? AND type = 'SOA'",
            [$zoneId]
        );

        if ($soaRecord) {
            $soaParts = explode(' ', $soaRecord['content']);
            $soaParts[2] = $newSerial; // Update serial in SOA
            $newSOA = implode(' ', $soaParts);

            $this->db->update($this->prefix . 'dns_records', [
                'content' => $newSOA,
            ], ['id' => $soaRecord['id']]);

            $this->db->update($this->prefix . 'dns_zones', [
                'soa' => $newSOA,
            ], ['id' => $zoneId]);
        }
    }

    /**
     * Write BIND zone file
     */
    private function writeZoneFile(int $zoneId): void
    {
        $zone = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}dns_zones WHERE id = ?",
            [$zoneId]
        );

        $records = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->prefix}dns_records WHERE zone_id = ? ORDER BY type, name",
            [$zoneId]
        );

        $zoneDir = '/etc/virpanel/dns/zones';
        if (!is_dir($zoneDir)) {
            mkdir($zoneDir, 0755, true);
        }

        $zoneFile = "{$zoneDir}/db.{$zone['domain']}";
        $content = ";; Zone file for {$zone['domain']}\n";
        $content .= ";; Generated by VirPanel on " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "\$TTL 14400\n\n";

        foreach ($records as $record) {
            $name = $record['name'];
            $ttl = $record['ttl'];
            $type = $record['type'];
            $priority = $record['priority'];
            $value = $record['content'];

            if ($type === 'MX') {
                $content .= sprintf("%-30s %6d IN %-6s %2d %s\n", $name, $ttl, $type, $priority, $value);
            } else {
                $content .= sprintf("%-30s %6d IN %-6s %s\n", $name, $ttl, $type, $value);
            }
        }

        file_put_contents($zoneFile, $content);
        chmod($zoneFile, 0644);

        logger("DNS zone file written: {$zoneFile}");
    }

    /**
     * Remove zone file
     */
    private function removeZoneFile(string $domain): void
    {
        $zoneFile = "/etc/virpanel/dns/zones/db.{$domain}";
        if (file_exists($zoneFile)) {
            @unlink($zoneFile);
            logger("DNS zone file removed: {$zoneFile}");
        }
    }

    /**
     * Reload DNS server (BIND)
     */
    private function reloadDNSServer(): void
    {
        // In production: exec('rndc reload');
        logger('DNS server reload requested');
    }
}
