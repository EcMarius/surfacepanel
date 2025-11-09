<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VirPanel\Core\Application;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class UserDashboardController
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

    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get account information
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ? AND status = 'active'",
            [$user->getId()]
        );

        if (!$account) {
            return new Response($this->template->render('errors/no-account.html.twig'), 403);
        }

        // Get package limits
        $package = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}packages WHERE id = ?",
            [$account['package_id']]
        );

        // Calculate statistics
        $stats = [
            'disk_usage' => $this->getDiskUsage($account['username']),
            'bandwidth_usage' => $this->getBandwidthUsage($account['username']),
            'email_accounts' => $this->getEmailAccountsCount($account['id']),
            'databases' => $this->getDatabasesCount($account['id']),
            'ftp_accounts' => $this->getFTPAccountsCount($account['id']),
            'domains' => $this->getDomainsCount($account['id']),
            'subdomains' => $this->getSubdomainsCount($account['id']),
            'parked_domains' => $this->getParkedDomainsCount($account['id']),
            'ssl_certificates' => $this->getSSLCertificatesCount($account['id']),
            'cron_jobs' => $this->getCronJobsCount($account['id']),
            'backups' => $this->getBackupsCount($account['id']),
        ];

        // Get package limits
        $limits = [
            'disk_quota' => $package['disk_quota'] ?? -1, // -1 = unlimited
            'bandwidth_quota' => $package['bandwidth_quota'] ?? -1,
            'email_accounts' => $package['email_accounts'] ?? -1,
            'databases' => $package['databases'] ?? -1,
            'ftp_accounts' => $package['ftp_accounts'] ?? -1,
            'addon_domains' => $package['addon_domains'] ?? -1,
            'subdomains' => $package['subdomains'] ?? -1,
            'parked_domains' => $package['parked_domains'] ?? -1,
        ];

        // Get recent activity
        $recent_activity = $this->getRecentActivity($account['id'], 10);

        // Calculate usage percentages
        $usage_percentages = [
            'disk' => $this->calculatePercentage($stats['disk_usage'], $limits['disk_quota']),
            'bandwidth' => $this->calculatePercentage($stats['bandwidth_usage'], $limits['bandwidth_quota']),
            'email_accounts' => $this->calculatePercentage($stats['email_accounts'], $limits['email_accounts']),
            'databases' => $this->calculatePercentage($stats['databases'], $limits['databases']),
            'ftp_accounts' => $this->calculatePercentage($stats['ftp_accounts'], $limits['ftp_accounts']),
            'addon_domains' => $this->calculatePercentage($stats['domains'], $limits['addon_domains']),
        ];

        // Get system information
        $system_info = [
            'php_version' => phpversion(),
            'mysql_version' => $this->getMySQLVersion(),
            'server_load' => $this->getServerLoad(),
            'server_uptime' => $this->getServerUptime(),
        ];

        return new Response($this->template->render('user/dashboard/index.html.twig', [
            'account' => $account,
            'package' => $package,
            'stats' => $stats,
            'limits' => $limits,
            'usage_percentages' => $usage_percentages,
            'recent_activity' => $recent_activity,
            'system_info' => $system_info,
        ]));
    }

    private function getDiskUsage(string $username): int
    {
        // Get disk usage in MB
        $homeDir = "/home/{$username}";

        if (!is_dir($homeDir)) {
            return 0;
        }

        // Use du command to calculate disk usage
        $output = shell_exec("du -sm " . escapeshellarg($homeDir) . " 2>/dev/null | cut -f1");

        return (int)trim($output ?: '0');
    }

    private function getBandwidthUsage(string $username): int
    {
        // Get bandwidth usage for current month in MB
        $currentMonth = date('Y-m');

        $result = $this->db->fetchOne(
            "SELECT SUM(bytes_sent + bytes_received) / 1024 / 1024 as bandwidth
             FROM {$this->prefix}bandwidth_logs
             WHERE username = ? AND DATE_FORMAT(logged_at, '%Y-%m') = ?",
            [$username, $currentMonth]
        );

        return (int)($result ?: 0);
    }

    private function getEmailAccountsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}email_accounts WHERE account_id = ?",
            [$accountId]
        );
    }

    private function getDatabasesCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}databases WHERE account_id = ?",
            [$accountId]
        );
    }

    private function getFTPAccountsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ftp_accounts WHERE account_id = ?",
            [$accountId]
        );
    }

    private function getDomainsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}addon_domains WHERE account_id = ?",
            [$accountId]
        );
    }

    private function getSubdomainsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}subdomains WHERE account_id = ?",
            [$accountId]
        );
    }

    private function getParkedDomainsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}parked_domains WHERE account_id = ?",
            [$accountId]
        );
    }

    private function getSSLCertificatesCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}ssl_certificates
             WHERE account_id = ? AND status = 'active'",
            [$accountId]
        );
    }

    private function getCronJobsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}cron_jobs
             WHERE account_id = ? AND status = 'active'",
            [$accountId]
        );
    }

    private function getBackupsCount(int $accountId): int
    {
        return (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}backups
             WHERE account_id = ? AND status = 'completed'",
            [$accountId]
        );
    }

    private function getRecentActivity(int $accountId, int $limit = 10): array
    {
        // Get recent activity from audit logs
        $activities = $this->db->fetchAllAssociative(
            "SELECT action, description, ip_address, created_at
             FROM {$this->prefix}audit_logs
             WHERE account_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$accountId, $limit],
            ['integer', 'integer']
        );

        return $activities;
    }

    private function calculatePercentage(int $used, int $total): float
    {
        if ($total == -1 || $total == 0) {
            // Unlimited or zero quota
            return 0;
        }

        $percentage = ($used / $total) * 100;
        return min(100, round($percentage, 1));
    }

    private function getMySQLVersion(): string
    {
        try {
            $version = $this->db->fetchOne("SELECT VERSION()");
            return $version ?: 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getServerLoad(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return sprintf("%.2f, %.2f, %.2f", $load[0], $load[1], $load[2]);
        }

        return 'N/A';
    }

    private function getServerUptime(): string
    {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = explode(' ', $uptime)[0];

            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);

            return sprintf("%d days, %d hours, %d minutes", $days, $hours, $minutes);
        }

        return 'N/A';
    }
}
