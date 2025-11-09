<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Application;
use Doctrine\DBAL\Connection;

class IPAddressController extends Controller
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
     * Display IP addresses overview
     */
    public function index(Request $request): Response
    {
        // Get all IP addresses
        $ipAddresses = $this->db->fetchAllAssociative(
            "SELECT
                i.*,
                COUNT(a.id) as account_count
             FROM {$this->prefix}ip_addresses i
             LEFT JOIN {$this->prefix}accounts a ON a.dedicated_ip = i.ip_address
             GROUP BY i.id
             ORDER BY i.created_at DESC"
        );

        // Count stats
        $totalIPs = count($ipAddresses);
        $dedicatedIPs = count(array_filter($ipAddresses, fn($ip) => $ip['type'] === 'dedicated'));
        $sharedIPs = count(array_filter($ipAddresses, fn($ip) => $ip['type'] === 'shared'));
        $availableIPs = count(array_filter($ipAddresses, fn($ip) => $ip['status'] === 'available'));

        return new Response($this->template->render('admin/ip-addresses/index.html.twig', [
            'ipAddresses' => $ipAddresses,
            'totalIPs' => $totalIPs,
            'dedicatedIPs' => $dedicatedIPs,
            'sharedIPs' => $sharedIPs,
            'availableIPs' => $availableIPs,
        ]));
    }

    /**
     * Add IP address to pool
     */
    public function store(Request $request): Response
    {
        $ipAddress = trim($request->request->get('ip_address'));
        $type = $request->request->get('type', 'shared');
        $nameserver = trim($request->request->get('nameserver', ''));

        // Validation
        $errors = [];

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $errors[] = 'Invalid IP address format';
        }

        // Check if IP already exists
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ip_addresses WHERE ip_address = ?",
            [$ipAddress]
        );

        if ($exists) {
            $errors[] = 'IP address already exists in the pool';
        }

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = $request->request->all();
            return new RedirectResponse('/admin/ip-addresses');
        }

        try {
            $this->db->beginTransaction();

            // Detect IP version
            $ipVersion = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6';

            // Insert IP address
            $this->db->insert($this->prefix . 'ip_addresses', [
                'ip_address' => $ipAddress,
                'ip_version' => $ipVersion,
                'type' => $type,
                'nameserver' => $nameserver ?: null,
                'status' => 'available',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // If this is the first shared IP, make it default
            if ($type === 'shared') {
                $sharedCount = $this->db->fetchOne(
                    "SELECT COUNT(*) FROM {$this->prefix}ip_addresses WHERE type = 'shared'"
                );

                if ($sharedCount == 1) {
                    $this->db->executeStatement(
                        "UPDATE {$this->prefix}ip_addresses SET is_default = 1 WHERE ip_address = ?",
                        [$ipAddress]
                    );
                }
            }

            $this->db->commit();

            $_SESSION['success'] = "IP address {$ipAddress} added successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to add IP address: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/ip-addresses');
    }

    /**
     * Update IP address
     */
    public function update(Request $request, int $id): Response
    {
        $type = $request->request->get('type');
        $nameserver = trim($request->request->get('nameserver', ''));
        $ptr = trim($request->request->get('ptr', ''));

        try {
            $this->db->update($this->prefix . 'ip_addresses', [
                'type' => $type,
                'nameserver' => $nameserver ?: null,
                'ptr_record' => $ptr ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            $_SESSION['success'] = 'IP address updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update IP address: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/ip-addresses');
    }

    /**
     * Set default shared IP
     */
    public function setDefault(Request $request, int $id): Response
    {
        try {
            $this->db->beginTransaction();

            // Remove default flag from all IPs
            $this->db->executeStatement(
                "UPDATE {$this->prefix}ip_addresses SET is_default = 0"
            );

            // Set new default
            $this->db->update($this->prefix . 'ip_addresses', [
                'is_default' => 1,
            ], ['id' => $id]);

            $this->db->commit();

            $_SESSION['success'] = 'Default IP address updated successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to set default IP: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/ip-addresses');
    }

    /**
     * Delete IP address
     */
    public function delete(Request $request, int $id): Response
    {
        try {
            $ip = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ip_addresses WHERE id = ?",
                [$id]
            );

            if (!$ip) {
                $_SESSION['error'] = 'IP address not found';
                return new RedirectResponse('/admin/ip-addresses');
            }

            // Check if IP is in use
            $accountsUsing = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$this->prefix}accounts WHERE dedicated_ip = ?",
                [$ip['ip_address']]
            );

            if ($accountsUsing > 0) {
                $_SESSION['error'] = "Cannot delete IP address. It is assigned to {$accountsUsing} account(s)";
                return new RedirectResponse('/admin/ip-addresses');
            }

            // Check if it's the default IP
            if ($ip['is_default']) {
                $_SESSION['error'] = 'Cannot delete the default shared IP address';
                return new RedirectResponse('/admin/ip-addresses');
            }

            $this->db->delete($this->prefix . 'ip_addresses', ['id' => $id]);

            $_SESSION['success'] = "IP address {$ip['ip_address']} deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete IP address: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/ip-addresses');
    }

    /**
     * Assign dedicated IP to account
     */
    public function assignToAccount(Request $request): Response
    {
        $ipId = (int)$request->request->get('ip_id');
        $accountId = (int)$request->request->get('account_id');

        try {
            $this->db->beginTransaction();

            $ip = $this->db->fetchAssociative(
                "SELECT * FROM {$this->prefix}ip_addresses WHERE id = ?",
                [$ipId]
            );

            if (!$ip) {
                throw new \Exception('IP address not found');
            }

            if ($ip['type'] !== 'dedicated') {
                throw new \Exception('Only dedicated IPs can be assigned to accounts');
            }

            // Update account with dedicated IP
            $this->db->update($this->prefix . 'accounts', [
                'dedicated_ip' => $ip['ip_address'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $accountId]);

            // Update IP status
            $this->db->update($this->prefix . 'ip_addresses', [
                'status' => 'assigned',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $ipId]);

            $this->db->commit();

            $_SESSION['success'] = "Dedicated IP {$ip['ip_address']} assigned to account successfully";
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to assign IP: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/ip-addresses');
    }

    /**
     * Remove dedicated IP from account
     */
    public function removeFromAccount(Request $request, int $accountId): Response
    {
        try {
            $this->db->beginTransaction();

            $account = $this->db->fetchAssociative(
                "SELECT dedicated_ip FROM {$this->prefix}accounts WHERE id = ?",
                [$accountId]
            );

            if (!$account || !$account['dedicated_ip']) {
                throw new \Exception('Account has no dedicated IP');
            }

            // Remove IP from account
            $this->db->update($this->prefix . 'accounts', [
                'dedicated_ip' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $accountId]);

            // Update IP status to available
            $this->db->update($this->prefix . 'ip_addresses', [
                'status' => 'available',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['ip_address' => $account['dedicated_ip']]);

            $this->db->commit();

            $_SESSION['success'] = 'Dedicated IP removed from account successfully';
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = 'Failed to remove IP: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/accounts');
    }

    /**
     * Update PTR record
     */
    public function updatePTR(Request $request, int $id): Response
    {
        $ptrRecord = trim($request->request->get('ptr_record'));

        try {
            // Validate PTR record format (should be a valid domain)
            if ($ptrRecord && !preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $ptrRecord)) {
                throw new \Exception('Invalid PTR record format');
            }

            $this->db->update($this->prefix . 'ip_addresses', [
                'ptr_record' => $ptrRecord ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // In production, update actual DNS PTR record
            $this->updateDNSPTR($id, $ptrRecord);

            $_SESSION['success'] = 'PTR record updated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update PTR record: ' . $e->getMessage();
        }

        return new RedirectResponse('/admin/ip-addresses');
    }

    /**
     * Update DNS PTR record (reverse DNS)
     */
    private function updateDNSPTR(int $ipId, string $ptrRecord): void
    {
        // In production, this would update the actual DNS server
        // For now, just log it
        logger("PTR record updated for IP ID {$ipId}: {$ptrRecord}");
    }
}
