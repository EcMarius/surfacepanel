<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WAFConfig;
use App\Models\WAFLog;
use App\Models\WAFWhitelist;
use App\Models\WAFBlacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class WAFController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
    }

    /**
     * Display WAF dashboard
     */
    public function index()
    {
        $config = WAFConfig::getInstance();

        // Get statistics for last 7 days
        $stats = WAFLog::getStatistics(null, 7);
        $topIPs = WAFLog::getTopAttackingIPs(10);
        $topURLs = WAFLog::getTopAttackedURLs(10);

        // Get recent critical attacks
        $recentAttacks = WAFLog::critical()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Count whitelist/blacklist entries
        $whitelistCount = WAFWhitelist::where('is_global', true)->count();
        $blacklistCount = WAFBlacklist::where('is_global', true)->count();

        return view('admin.waf.index', compact(
            'config',
            'stats',
            'topIPs',
            'topURLs',
            'recentAttacks',
            'whitelistCount',
            'blacklistCount'
        ));
    }

    /**
     * Update WAF configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_enabled' => 'required|boolean',
            'mode' => 'required|in:off,detection,blocking',
            'use_owasp_crs' => 'required|boolean',
            'paranoia_level' => 'required|integer|min:1|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = WAFConfig::getInstance();
            $config->update($request->only([
                'is_enabled',
                'mode',
                'use_owasp_crs',
                'paranoia_level',
            ]));

            // Generate ModSecurity configuration
            $this->generateModSecurityConfig($config);

            return response()->json([
                'success' => true,
                'message' => 'WAF configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View attack logs
     */
    public function logs(Request $request)
    {
        $query = WAFLog::with('account')
            ->orderByDesc('created_at');

        // Filters
        if ($request->has('attack_type') && $request->attack_type !== 'all') {
            $query->where('attack_type', $request->attack_type);
        }

        if ($request->has('severity') && $request->severity !== 'all') {
            $query->where('severity', $request->severity);
        }

        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }

        if ($request->has('ip') && $request->ip) {
            $query->where('client_ip', 'like', '%' . $request->ip . '%');
        }

        $logs = $query->paginate(50);

        return view('admin.waf.logs', compact('logs'));
    }

    /**
     * View whitelist
     */
    public function whitelist()
    {
        $entries = WAFWhitelist::where('is_global', true)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.waf.whitelist', compact('entries'));
    }

    /**
     * Add to whitelist
     */
    public function addWhitelist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:ip,ip_range,user_agent,uri_pattern',
            'value' => 'required|string',
            'description' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Validate IP/CIDR format if applicable
            if ($request->type === 'ip' && !filter_var($request->value, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid IP address format',
                ], 422);
            }

            WAFWhitelist::create([
                'type' => $request->type,
                'value' => $request->value,
                'description' => $request->description,
                'is_global' => true,
                'expires_at' => $request->expires_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entry added to whitelist successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add whitelist entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove from whitelist
     */
    public function removeWhitelist($id)
    {
        try {
            $entry = WAFWhitelist::findOrFail($id);
            $entry->delete();

            return response()->json([
                'success' => true,
                'message' => 'Entry removed from whitelist',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View blacklist
     */
    public function blacklist()
    {
        $entries = WAFBlacklist::where('is_global', true)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.waf.blacklist', compact('entries'));
    }

    /**
     * Add to blacklist
     */
    public function addBlacklist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:ip,ip_range,user_agent,uri_pattern',
            'value' => 'required|string',
            'reason' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Validate IP format if applicable
            if ($request->type === 'ip' && !filter_var($request->value, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid IP address format',
                ], 422);
            }

            WAFBlacklist::create([
                'type' => $request->type,
                'value' => $request->value,
                'reason' => $request->reason,
                'is_global' => true,
                'expires_at' => $request->expires_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entry added to blacklist successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add blacklist entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove from blacklist
     */
    public function removeBlacklist($id)
    {
        try {
            $entry = WAFBlacklist::findOrFail($id);
            $entry->delete();

            return response()->json([
                'success' => true,
                'message' => 'Entry removed from blacklist',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate ModSecurity configuration file
     */
    private function generateModSecurityConfig(WAFConfig $config): void
    {
        $configPath = '/etc/modsecurity/virpanel.conf';

        $content = "# VirPanel ModSecurity Configuration\n";
        $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        if (!$config->is_enabled) {
            $content .= "SecRuleEngine Off\n";
        } else {
            $content .= match($config->mode) {
                'detection' => "SecRuleEngine DetectionOnly\n",
                'blocking' => "SecRuleEngine On\n",
                default => "SecRuleEngine Off\n",
            };
        }

        $content .= "\n# OWASP Core Rule Set\n";
        if ($config->use_owasp_crs) {
            $content .= "SecRule PARANOIA_LEVEL \"@eq {$config->paranoia_level}\"\n";
            $content .= "Include /usr/share/modsecurity-crs/*.conf\n";
            $content .= "Include /usr/share/modsecurity-crs/rules/*.conf\n";
        }

        // Write configuration
        @file_put_contents($configPath, $content);

        // Reload web server
        exec('systemctl reload nginx 2>&1', $output, $returnCode);
    }

    /**
     * Get WAF statistics API
     */
    public function statistics(Request $request)
    {
        $days = $request->input('days', 7);
        $stats = WAFLog::getStatistics(null, $days);

        return response()->json([
            'success' => true,
            'statistics' => $stats,
        ]);
    }
}
