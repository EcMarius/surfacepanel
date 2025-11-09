<?php

namespace VirPanel\Core\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use VirPanel\Core\Application;
use VirPanel\Core\Template\TemplateEngine;
use VirPanel\Core\Auth\Auth;
use Doctrine\DBAL\Connection;

class StatisticsController
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

        // Get account
        $account = $this->db->fetchAssociative(
            "SELECT * FROM {$this->prefix}accounts WHERE user_id = ?",
            [$user->getId()]
        );

        if (!$account) {
            return new Response($this->template->render('errors/no-account.html.twig'), 403);
        }

        // Get domain filter (default to primary domain)
        $domain = $request->query->get('domain', $account['domain']);

        // Get date range (default to last 30 days)
        $startDate = $request->query->get('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->query->get('end_date', date('Y-m-d'));

        // Get available domains for this account
        $domains = $this->getAccountDomains($account['id']);

        // Get statistics
        $stats = [
            'overview' => $this->getOverviewStats($account['username'], $domain, $startDate, $endDate),
            'popular_pages' => $this->getPopularPages($account['username'], $domain, $startDate, $endDate, 20),
            'referrers' => $this->getTopReferrers($account['username'], $domain, $startDate, $endDate, 20),
            'browsers' => $this->getBrowserStats($account['username'], $domain, $startDate, $endDate),
            'os' => $this->getOSStats($account['username'], $domain, $startDate, $endDate),
            'countries' => $this->getCountryStats($account['username'], $domain, $startDate, $endDate),
            'error_pages' => $this->getErrorPages($account['username'], $domain, $startDate, $endDate, 20),
            'bandwidth_by_day' => $this->getBandwidthByDay($account['username'], $domain, $startDate, $endDate),
            'visitors_by_hour' => $this->getVisitorsByHour($account['username'], $domain, $startDate, $endDate),
        ];

        return new Response($this->template->render('user/statistics/index.html.twig', [
            'account' => $account,
            'domains' => $domains,
            'selected_domain' => $domain,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'stats' => $stats,
        ]));
    }

    private function getAccountDomains(int $accountId): array
    {
        // Get primary domain
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

        // Get subdomains
        $subdomains = $this->db->fetchAllAssociative(
            "SELECT CONCAT(subdomain, '.', parent_domain) as domain
             FROM {$this->prefix}subdomains
             WHERE account_id = ?",
            [$accountId]
        );

        foreach ($subdomains as $subdomain) {
            $domains[] = $subdomain['domain'];
        }

        return $domains;
    }

    private function getOverviewStats(string $username, string $domain, string $startDate, string $endDate): array
    {
        // Total visits
        $totalVisits = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?",
            [$username, $domain, $startDate, $endDate]
        );

        // Unique visitors (by IP)
        $uniqueVisitors = (int)$this->db->fetchOne(
            "SELECT COUNT(DISTINCT remote_addr) FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?",
            [$username, $domain, $startDate, $endDate]
        );

        // Total bandwidth
        $totalBandwidth = (int)$this->db->fetchOne(
            "SELECT SUM(bytes_sent) FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?",
            [$username, $domain, $startDate, $endDate]
        );

        // Page views (status 200-299)
        $pageViews = (int)$this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND status >= 200 AND status < 300
             AND DATE(timestamp) BETWEEN ? AND ?",
            [$username, $domain, $startDate, $endDate]
        );

        // Average daily visitors
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
        $avgDailyVisitors = $daysDiff > 0 ? round($uniqueVisitors / $daysDiff, 1) : 0;

        return [
            'total_visits' => $totalVisits,
            'unique_visitors' => $uniqueVisitors,
            'page_views' => $pageViews,
            'total_bandwidth' => $totalBandwidth,
            'total_bandwidth_mb' => round($totalBandwidth / 1024 / 1024, 2),
            'avg_daily_visitors' => $avgDailyVisitors,
        ];
    }

    private function getPopularPages(string $username, string $domain, string $startDate, string $endDate, int $limit): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT request_uri, COUNT(*) as hits, SUM(bytes_sent) as bandwidth
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND status >= 200 AND status < 300
             AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY request_uri
             ORDER BY hits DESC
             LIMIT ?",
            [$username, $domain, $startDate, $endDate, $limit],
            ['string', 'string', 'string', 'string', 'integer']
        );
    }

    private function getTopReferrers(string $username, string $domain, string $startDate, string $endDate, int $limit): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT http_referer, COUNT(*) as hits
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND http_referer != '' AND http_referer != '-'
             AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY http_referer
             ORDER BY hits DESC
             LIMIT ?",
            [$username, $domain, $startDate, $endDate, $limit],
            ['string', 'string', 'string', 'string', 'integer']
        );
    }

    private function getBrowserStats(string $username, string $domain, string $startDate, string $endDate): array
    {
        $results = $this->db->fetchAllAssociative(
            "SELECT http_user_agent, COUNT(*) as count
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY http_user_agent
             ORDER BY count DESC
             LIMIT 100",
            [$username, $domain, $startDate, $endDate]
        );

        // Parse user agents to get browser names
        $browsers = [];
        foreach ($results as $row) {
            $browser = $this->parseBrowser($row['http_user_agent']);
            if (!isset($browsers[$browser])) {
                $browsers[$browser] = 0;
            }
            $browsers[$browser] += $row['count'];
        }

        arsort($browsers);
        return array_slice($browsers, 0, 10, true);
    }

    private function getOSStats(string $username, string $domain, string $startDate, string $endDate): array
    {
        $results = $this->db->fetchAllAssociative(
            "SELECT http_user_agent, COUNT(*) as count
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY http_user_agent
             ORDER BY count DESC
             LIMIT 100",
            [$username, $domain, $startDate, $endDate]
        );

        // Parse user agents to get OS names
        $os = [];
        foreach ($results as $row) {
            $osName = $this->parseOS($row['http_user_agent']);
            if (!isset($os[$osName])) {
                $os[$osName] = 0;
            }
            $os[$osName] += $row['count'];
        }

        arsort($os);
        return array_slice($os, 0, 10, true);
    }

    private function getCountryStats(string $username, string $domain, string $startDate, string $endDate): array
    {
        // Note: This requires GeoIP database integration
        // For now, return placeholder data
        return [];
    }

    private function getErrorPages(string $username, string $domain, string $startDate, string $endDate, int $limit): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT request_uri, status, COUNT(*) as hits
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND status >= 400
             AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY request_uri, status
             ORDER BY hits DESC
             LIMIT ?",
            [$username, $domain, $startDate, $endDate, $limit],
            ['string', 'string', 'string', 'string', 'integer']
        );
    }

    private function getBandwidthByDay(string $username, string $domain, string $startDate, string $endDate): array
    {
        $results = $this->db->fetchAllAssociative(
            "SELECT DATE(timestamp) as date, SUM(bytes_sent) as bandwidth
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY DATE(timestamp)
             ORDER BY date ASC",
            [$username, $domain, $startDate, $endDate]
        );

        $data = [];
        foreach ($results as $row) {
            $data[$row['date']] = round($row['bandwidth'] / 1024 / 1024, 2); // Convert to MB
        }

        return $data;
    }

    private function getVisitorsByHour(string $username, string $domain, string $startDate, string $endDate): array
    {
        $results = $this->db->fetchAllAssociative(
            "SELECT HOUR(timestamp) as hour, COUNT(DISTINCT remote_addr) as visitors
             FROM {$this->prefix}access_logs
             WHERE username = ? AND host = ? AND DATE(timestamp) BETWEEN ? AND ?
             GROUP BY HOUR(timestamp)
             ORDER BY hour ASC",
            [$username, $domain, $startDate, $endDate]
        );

        $data = array_fill(0, 24, 0);
        foreach ($results as $row) {
            $data[(int)$row['hour']] = (int)$row['visitors'];
        }

        return $data;
    }

    private function parseBrowser(string $userAgent): string
    {
        if (stripos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (stripos($userAgent, 'Edg') !== false) {
            return 'Edge';
        } elseif (stripos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (stripos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR') !== false) {
            return 'Opera';
        } elseif (stripos($userAgent, 'MSIE') !== false || stripos($userAgent, 'Trident') !== false) {
            return 'Internet Explorer';
        } else {
            return 'Other';
        }
    }

    private function parseOS(string $userAgent): string
    {
        if (stripos($userAgent, 'Windows NT 10.0') !== false) {
            return 'Windows 10/11';
        } elseif (stripos($userAgent, 'Windows NT 6.3') !== false) {
            return 'Windows 8.1';
        } elseif (stripos($userAgent, 'Windows NT 6.2') !== false) {
            return 'Windows 8';
        } elseif (stripos($userAgent, 'Windows NT 6.1') !== false) {
            return 'Windows 7';
        } elseif (stripos($userAgent, 'Windows') !== false) {
            return 'Windows';
        } elseif (stripos($userAgent, 'Mac OS X') !== false) {
            return 'macOS';
        } elseif (stripos($userAgent, 'Android') !== false) {
            return 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            return 'iOS';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        } else {
            return 'Other';
        }
    }
}
