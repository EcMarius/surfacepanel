<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FirewallConfig;
use App\Models\FirewallDeny;
use App\Models\FirewallLoginFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FirewallController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
    }

    /**
     * Display firewall dashboard
     */
    public function index()
    {
        $config = FirewallConfig::getInstance();

        // Get statistics
        $deniedIPs = FirewallDeny::count();
        $bannedIPs = FirewallLoginFailure::where('is_banned', true)->count();
        $topBlocked = FirewallDeny::getTopBlocked(10);
        $recentBans = FirewallLoginFailure::getBannedIPs();
        $loginStats = FirewallLoginFailure::getStatistics(7);

        return view('admin.firewall.index', compact(
            'config',
            'deniedIPs',
            'bannedIPs',
            'topBlocked',
            'recentBans',
            'loginStats'
        ));
    }

    /**
     * Update firewall configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_enabled' => 'required|boolean',
            'default_policy' => 'required|in:accept,drop,reject',
            'block_ping' => 'required|boolean',
            'syn_flood_protection' => 'required|boolean',
            'connection_limit' => 'required|integer|min:1|max:1000',
            'port_scan_threshold' => 'required|integer|min:1|max:100',
            'login_failure_threshold' => 'required|integer|min:1|max:20',
            'login_failure_ban_time' => 'required|integer|min:60|max:86400',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = FirewallConfig::getInstance();
            $config->update($request->only([
                'is_enabled',
                'default_policy',
                'block_ping',
                'syn_flood_protection',
                'connection_limit',
                'port_scan_threshold',
                'login_failure_threshold',
                'login_failure_ban_time',
            ]));

            // Apply iptables rules
            $this->applyFirewallRules($config);

            return response()->json([
                'success' => true,
                'message' => 'Firewall configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Block an IP address
     */
    public function blockIP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|ip',
            'reason' => 'nullable|string',
            'duration' => 'nullable|integer|min:60', // seconds
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $duration = $request->duration ?? null; // null = permanent

            FirewallDeny::blockIP(
                $request->ip_address,
                $request->reason ?? 'Manually blocked',
                'manual',
                $duration
            );

            // Apply iptables rule
            $this->applyIPTablesBlock($request->ip_address);

            return response()->json([
                'success' => true,
                'message' => "IP {$request->ip_address} blocked successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to block IP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unblock an IP address
     */
    public function unblockIP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ip_address' => 'required|ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $unblocked = FirewallDeny::unblockIP($request->ip_address);

            if (!$unblocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'IP address not found in block list',
                ], 404);
            }

            // Remove iptables rule
            $this->removeIPTablesBlock($request->ip_address);

            // Also clear login failures
            FirewallLoginFailure::clearFailures($request->ip_address);

            return response()->json([
                'success' => true,
                'message' => "IP {$request->ip_address} unblocked successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unblock IP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View blocked IPs
     */
    public function blockedIPs()
    {
        $blockedIPs = FirewallDeny::orderByDesc('created_at')->paginate(50);
        $bannedIPs = FirewallLoginFailure::where('is_banned', true)
            ->orderByDesc('last_attempt_at')
            ->get();

        return view('admin.firewall.blocked-ips', compact('blockedIPs', 'bannedIPs'));
    }

    /**
     * View login failures
     */
    public function loginFailures()
    {
        $failures = FirewallLoginFailure::orderByDesc('last_attempt_at')
            ->paginate(50);

        $stats = FirewallLoginFailure::getStatistics(30);

        return view('admin.firewall.login-failures', compact('failures', 'stats'));
    }

    /**
     * Clean up expired blocks and bans
     */
    public function cleanup()
    {
        try {
            $expiredBlocks = FirewallDeny::cleanExpired();
            $expiredBans = FirewallLoginFailure::cleanExpired();

            return response()->json([
                'success' => true,
                'message' => "Cleanup complete: {$expiredBlocks} expired blocks and {$expiredBans} expired bans removed",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply firewall rules using iptables
     */
    private function applyFirewallRules(FirewallConfig $config): void
    {
        if (!$config->is_enabled) {
            // Flush all rules if disabled
            exec('iptables -F 2>&1', $output, $returnCode);
            exec('iptables -P INPUT ACCEPT 2>&1');
            exec('iptables -P OUTPUT ACCEPT 2>&1');
            exec('iptables -P FORWARD ACCEPT 2>&1');
            return;
        }

        // Set default policy
        $policy = strtoupper($config->default_policy);
        exec("iptables -P INPUT {$policy} 2>&1");
        exec("iptables -P FORWARD {$policy} 2>&1");

        // Allow loopback
        exec('iptables -A INPUT -i lo -j ACCEPT 2>&1');

        // Allow established connections
        exec('iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT 2>&1');

        // Block ping if configured
        if ($config->block_ping) {
            exec('iptables -A INPUT -p icmp --icmp-type echo-request -j DROP 2>&1');
        }

        // SYN flood protection
        if ($config->syn_flood_protection) {
            exec('iptables -N syn_flood 2>&1');
            exec('iptables -A INPUT -p tcp --syn -j syn_flood 2>&1');
            exec('iptables -A syn_flood -m limit --limit 1/s --limit-burst 3 -j RETURN 2>&1');
            exec('iptables -A syn_flood -j DROP 2>&1');
        }

        // Connection limiting
        if ($config->connection_limit > 0) {
            exec("iptables -A INPUT -p tcp -m connlimit --connlimit-above {$config->connection_limit} -j DROP 2>&1");
        }

        // Save rules
        exec('iptables-save > /etc/iptables/rules.v4 2>&1');
    }

    /**
     * Apply IP block using iptables
     */
    private function applyIPTablesBlock(string $ip): void
    {
        exec("iptables -I INPUT -s {$ip} -j DROP 2>&1", $output, $returnCode);
        exec('iptables-save > /etc/iptables/rules.v4 2>&1');
    }

    /**
     * Remove IP block from iptables
     */
    private function removeIPTablesBlock(string $ip): void
    {
        exec("iptables -D INPUT -s {$ip} -j DROP 2>&1", $output, $returnCode);
        exec('iptables-save > /etc/iptables/rules.v4 2>&1');
    }

    /**
     * Get firewall statistics
     */
    public function statistics()
    {
        $stats = [
            'denied_ips' => FirewallDeny::count(),
            'permanent_blocks' => FirewallDeny::where('is_permanent', true)->count(),
            'temporary_blocks' => FirewallDeny::where('is_permanent', false)->count(),
            'banned_ips' => FirewallLoginFailure::where('is_banned', true)->count(),
            'login_failures' => FirewallLoginFailure::getStatistics(7),
            'top_blocked' => FirewallDeny::getTopBlocked(10),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }
}
