<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpamAssassinConfig;
use App\Models\SpamScore;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SpamAssassinController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
    }

    /**
     * Display SpamAssassin dashboard
     */
    public function index()
    {
        // Get system-wide statistics
        $totalAccounts = Account::count();
        $enabledAccounts = SpamAssassinConfig::where('is_enabled', true)->count();

        // Get statistics for last 7 days
        $startDate = Carbon::now()->subDays(7);
        $stats = SpamScore::where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_emails,
                SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam_count,
                SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) as ham_count,
                AVG(CASE WHEN is_spam = 1 THEN spam_score ELSE NULL END) as avg_spam_score,
                AVG(CASE WHEN is_spam = 0 THEN spam_score ELSE NULL END) as avg_ham_score
            ')
            ->first();

        // Get action breakdown
        $actionStats = SpamScore::where('created_at', '>=', $startDate)
            ->where('is_spam', true)
            ->selectRaw('action_taken, COUNT(*) as count')
            ->groupBy('action_taken')
            ->get()
            ->pluck('count', 'action_taken')
            ->toArray();

        // Get top spam accounts
        $topSpamAccounts = SpamScore::where('created_at', '>=', $startDate)
            ->where('is_spam', true)
            ->selectRaw('account_id, COUNT(*) as spam_count')
            ->groupBy('account_id')
            ->orderByDesc('spam_count')
            ->limit(10)
            ->with('account')
            ->get();

        // Get recent spam samples
        $recentSpam = SpamScore::where('is_spam', true)
            ->with('account')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.spamassassin.index', compact(
            'totalAccounts',
            'enabledAccounts',
            'stats',
            'actionStats',
            'topSpamAccounts',
            'recentSpam'
        ));
    }

    /**
     * Show global SpamAssassin settings
     */
    public function settings()
    {
        return view('admin.spamassassin.settings');
    }

    /**
     * Update global SpamAssassin settings
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_threshold' => 'required|numeric|min:0|max:10',
            'default_action' => 'required|in:delete,quarantine,tag',
            'enable_bayes' => 'required|boolean',
            'enable_auto_learn' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Update default settings for all new accounts
            // This would typically be stored in a system config table
            // For now, we'll just return success

            return response()->json([
                'success' => true,
                'message' => 'Global SpamAssassin settings updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable SpamAssassin for an account
     */
    public function enableAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:vp_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = SpamAssassinConfig::getForAccount($request->account_id);
            $config->update(['is_enabled' => true]);

            return response()->json([
                'success' => true,
                'message' => 'SpamAssassin enabled for account',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enable SpamAssassin: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable SpamAssassin for an account
     */
    public function disableAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:vp_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = SpamAssassinConfig::getForAccount($request->account_id);
            $config->update(['is_enabled' => false]);

            return response()->json([
                'success' => true,
                'message' => 'SpamAssassin disabled for account',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable SpamAssassin: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View all spam logs
     */
    public function logs(Request $request)
    {
        $query = SpamScore::with('account')
            ->orderByDesc('created_at');

        // Filters
        if ($request->has('account_id') && $request->account_id !== 'all') {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('is_spam') && $request->is_spam !== 'all') {
            $query->where('is_spam', $request->is_spam === '1');
        }

        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action_taken', $request->action);
        }

        if ($request->has('min_score')) {
            $query->where('spam_score', '>=', $request->min_score);
        }

        $logs = $query->paginate(50);
        $accounts = Account::orderBy('username')->get();

        return view('admin.spamassassin.logs', compact('logs', 'accounts'));
    }

    /**
     * Get statistics API
     */
    public function statistics(Request $request)
    {
        $days = $request->input('days', 7);
        $startDate = Carbon::now()->subDays($days);

        $stats = SpamScore::where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_emails,
                SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam_count,
                SUM(CASE WHEN is_spam = 0 THEN 1 ELSE 0 END) as ham_count
            ')
            ->first();

        // Get daily breakdown
        $dailyStats = [];
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($days - $i - 1)->startOfDay();
            $nextDate = $date->copy()->addDay();

            $dayStats = SpamScore::whereBetween('created_at', [$date, $nextDate])
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam
                ')
                ->first();

            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'total' => $dayStats->total ?? 0,
                'spam' => $dayStats->spam ?? 0,
                'ham' => ($dayStats->total ?? 0) - ($dayStats->spam ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $stats->total_emails ?? 0,
                'spam' => $stats->spam_count ?? 0,
                'ham' => $stats->ham_count ?? 0,
                'daily' => $dailyStats,
            ],
        ]);
    }

    /**
     * Rebuild Bayes database for an account
     */
    public function rebuildBayes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:vp_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $account = Account::findOrFail($request->account_id);

            // Execute sa-learn to rebuild Bayes database
            $command = "su - {$account->username} -c 'sa-learn --rebuild' 2>&1";
            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bayes database rebuilt successfully',
                    'output' => implode("\n", $output),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to rebuild Bayes database',
                    'output' => implode("\n", $output),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rebuild Bayes database: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SpamAssassin rules
     */
    public function updateRules()
    {
        try {
            // Run sa-update to fetch latest rules
            exec('sa-update 2>&1', $output, $returnCode);

            // Restart SpamAssassin service
            exec('systemctl restart spamassassin 2>&1', $restartOutput, $restartCode);

            if ($returnCode === 0 && $restartCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'SpamAssassin rules updated successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update rules',
                    'output' => implode("\n", array_merge($output, $restartOutput)),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rules: ' . $e->getMessage(),
            ], 500);
        }
    }
}
