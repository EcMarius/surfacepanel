<?php

namespace VirPanel\Core\Http\Controllers;

use VirPanel\Core\Auth\Auth;
use VirPanel\Core\Template\TemplateEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin Dashboard Controller
 *
 * Handles WHM admin dashboard
 */
class AdminDashboardController
{
    /**
     * Template engine
     *
     * @var TemplateEngine
     */
    protected TemplateEngine $template;

    /**
     * Create a new controller instance
     */
    public function __construct()
    {
        $this->template = app('template');
    }

    /**
     * Show admin dashboard
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $db = app('database');
        $prefix = config('database.prefix', 'vp_');

        // Get statistics
        $stats = [
            'total_accounts' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}accounts"),
            'active_accounts' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}accounts WHERE status = 'active'"),
            'suspended_accounts' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}accounts WHERE status = 'suspended'"),
            'total_domains' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}domains"),
            'ssl_domains' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}domains WHERE ssl_enabled = 1"),
            'total_databases' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}databases"),
            'total_emails' => (int) $db->fetchOne("SELECT COUNT(*) FROM {$prefix}email_accounts"),
            'disk_used' => (int) $db->fetchOne("SELECT SUM(disk_used) FROM {$prefix}accounts"),
            'disk_total' => (int) $db->fetchOne("SELECT SUM(disk_quota) FROM {$prefix}accounts WHERE disk_quota > 0"),
        ];

        // Get recent accounts
        $recentAccounts = $db->fetchAllAssociative(
            "SELECT username, domain, status, created_at
             FROM {$prefix}accounts
             ORDER BY created_at DESC
             LIMIT 10"
        );

        // Get system info
        $systemInfo = [
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'load_average' => sys_getloadavg(),
        ];

        $content = $this->template->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_accounts' => $recentAccounts,
            'system_info' => $systemInfo,
        ]);

        return new Response($content);
    }
}
