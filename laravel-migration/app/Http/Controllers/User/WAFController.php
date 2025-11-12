<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WAFConfig;
use App\Models\WAFLog;
use App\Models\WAFWhitelist;
use App\Models\WAFBlacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WAFController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.user']);
    }

    /**
     * Display WAF dashboard for user's account
     */
    public function index()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        if (!$account) {
            abort(404, 'Account not found');
        }

        $config = WAFConfig::getInstance();

        // Get statistics for this account
        $stats = WAFLog::getStatistics($account->id, 7);
        $topIPs = WAFLog::getTopAttackingIPs(10, $account->id);
        $topURLs = WAFLog::getTopAttackedURLs(10, $account->id);

        // Get recent attacks on this account
        $recentAttacks = WAFLog::forAccount($account->id)
            ->critical()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('user.waf.index', compact(
            'config',
            'stats',
            'topIPs',
            'topURLs',
            'recentAttacks',
            'account'
        ));
    }

    /**
     * View attack logs for this account
     */
    public function logs(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $query = WAFLog::forAccount($account->id)
            ->orderByDesc('created_at');

        // Filters
        if ($request->has('attack_type') && $request->attack_type !== 'all') {
            $query->where('attack_type', $request->attack_type);
        }

        if ($request->has('severity') && $request->severity !== 'all') {
            $query->where('severity', $request->severity);
        }

        if ($request->has('domain') && $request->domain !== 'all') {
            $query->where('domain', $request->domain);
        }

        $logs = $query->paginate(50);

        // Get domains for filter
        $domains = WAFLog::forAccount($account->id)
            ->select('domain')
            ->distinct()
            ->pluck('domain');

        return view('user.waf.logs', compact('logs', 'domains'));
    }

    /**
     * View account whitelist
     */
    public function whitelist()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $entries = WAFWhitelist::where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->get();

        return view('user.waf.whitelist', compact('entries'));
    }

    /**
     * Add to account whitelist
     */
    public function addWhitelist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:ip,ip_range',
            'value' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::guard('user')->user();
            $account = $user->account;

            // Validate IP format
            if ($request->type === 'ip' && !filter_var($request->value, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid IP address format',
                ], 422);
            }

            // Check limit (max 50 whitelist entries per account)
            $count = WAFWhitelist::where('account_id', $account->id)->count();
            if ($count >= 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum whitelist entries (50) reached',
                ], 422);
            }

            WAFWhitelist::create([
                'account_id' => $account->id,
                'type' => $request->type,
                'value' => $request->value,
                'description' => $request->description,
                'is_global' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'IP added to whitelist successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add whitelist entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove from account whitelist
     */
    public function removeWhitelist($id)
    {
        try {
            $user = Auth::guard('user')->user();
            $account = $user->account;

            $entry = WAFWhitelist::where('account_id', $account->id)
                ->findOrFail($id);

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
     * Get log details
     */
    public function logDetails($id)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        try {
            $log = WAFLog::forAccount($account->id)->findOrFail($id);

            return view('user.waf.log-details', compact('log'));
        } catch (\Exception $e) {
            abort(404, 'Log entry not found');
        }
    }
}
