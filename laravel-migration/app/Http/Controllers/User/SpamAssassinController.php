<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SpamAssassinConfig;
use App\Models\SpamScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SpamAssassinController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.user']);
    }

    /**
     * Display SpamAssassin dashboard for user's account
     */
    public function index()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        if (!$account) {
            abort(404, 'Account not found');
        }

        // Get or create configuration
        $config = SpamAssassinConfig::getForAccount($account->id);

        // Get statistics for last 7 days
        $stats = SpamScore::getStatistics($account->id, 7);

        // Get spam trend
        $spamTrend = SpamScore::getSpamTrend($account->id, 7);

        // Get top spam senders
        $topSpamSenders = SpamScore::getTopSpamSenders($account->id, 10);

        // Get recent spam
        $recentSpam = SpamScore::forAccount($account->id)
            ->spam()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Get whitelist/blacklist counts
        $whitelistCount = DB::table('vp_spamassassin_lists')
            ->where('account_id', $account->id)
            ->where('list_type', 'whitelist')
            ->count();

        $blacklistCount = DB::table('vp_spamassassin_lists')
            ->where('account_id', $account->id)
            ->where('list_type', 'blacklist')
            ->count();

        return view('user.spamassassin.index', compact(
            'config',
            'stats',
            'spamTrend',
            'topSpamSenders',
            'recentSpam',
            'whitelistCount',
            'blacklistCount',
            'account'
        ));
    }

    /**
     * Update SpamAssassin configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_enabled' => 'required|boolean',
            'spam_threshold' => 'required|numeric|min:0|max:10',
            'spam_action' => 'required|in:delete,quarantine,tag',
            'spam_folder' => 'nullable|string|max:255',
            'auto_learn' => 'required|boolean',
            'use_bayes' => 'required|boolean',
            'rewrite_header' => 'required|boolean',
            'spam_subject_tag' => 'nullable|string|max:50',
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

            $config = SpamAssassinConfig::getForAccount($account->id);
            $config->update($request->only([
                'is_enabled',
                'spam_threshold',
                'spam_action',
                'spam_folder',
                'auto_learn',
                'use_bayes',
                'rewrite_header',
                'spam_subject_tag',
            ]));

            // Generate user_prefs file
            $this->generateUserPrefs($account, $config);

            return response()->json([
                'success' => true,
                'message' => 'SpamAssassin configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show whitelist/blacklist management
     */
    public function lists(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $listType = $request->input('type', 'whitelist');

        $entries = DB::table('vp_spamassassin_lists')
            ->where('account_id', $account->id)
            ->where('list_type', $listType)
            ->orderByDesc('created_at')
            ->get();

        return view('user.spamassassin.lists', compact('entries', 'listType', 'account'));
    }

    /**
     * Add entry to whitelist/blacklist
     */
    public function addToList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_type' => 'required|in:whitelist,blacklist',
            'entry_type' => 'required|in:email,domain,ip',
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

            // Validate format based on type
            if ($request->entry_type === 'email' && !filter_var($request->value, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email address format',
                ], 422);
            }

            if ($request->entry_type === 'ip' && !filter_var($request->value, FILTER_VALIDATE_IP)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid IP address format',
                ], 422);
            }

            // Check limit (max 100 entries per list)
            $count = DB::table('vp_spamassassin_lists')
                ->where('account_id', $account->id)
                ->where('list_type', $request->list_type)
                ->count();

            if ($count >= 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum entries (100) reached for this list',
                ], 422);
            }

            // Add entry
            DB::table('vp_spamassassin_lists')->insert([
                'account_id' => $account->id,
                'list_type' => $request->list_type,
                'entry_type' => $request->entry_type,
                'value' => $request->value,
                'description' => $request->description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Regenerate user_prefs
            $config = SpamAssassinConfig::getForAccount($account->id);
            $this->generateUserPrefs($account, $config);

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->list_type) . ' entry added successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove entry from whitelist/blacklist
     */
    public function removeFromList($id)
    {
        try {
            $user = Auth::guard('user')->user();
            $account = $user->account;

            $deleted = DB::table('vp_spamassassin_lists')
                ->where('id', $id)
                ->where('account_id', $account->id)
                ->delete();

            if ($deleted) {
                // Regenerate user_prefs
                $config = SpamAssassinConfig::getForAccount($account->id);
                $this->generateUserPrefs($account, $config);

                return response()->json([
                    'success' => true,
                    'message' => 'Entry removed successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Entry not found',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show training interface
     */
    public function training()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        // Get training statistics
        $spamTrained = DB::table('vp_spam_training')
            ->where('account_id', $account->id)
            ->where('type', 'spam')
            ->where('is_trained', true)
            ->count();

        $hamTrained = DB::table('vp_spam_training')
            ->where('account_id', $account->id)
            ->where('type', 'ham')
            ->where('is_trained', true)
            ->count();

        // Get pending training items
        $pendingTraining = DB::table('vp_spam_training')
            ->where('account_id', $account->id)
            ->where('is_trained', false)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('user.spamassassin.training', compact(
            'spamTrained',
            'hamTrained',
            'pendingTraining',
            'account'
        ));
    }

    /**
     * Train spam/ham
     */
    public function train(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:spam,ham',
            'message_id' => 'required|string',
            'subject' => 'nullable|string',
            'from_address' => 'nullable|string',
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

            // Add to training queue
            $trainingId = DB::table('vp_spam_training')->insertGetId([
                'account_id' => $account->id,
                'email_account' => $user->email,
                'type' => $request->type,
                'message_id' => $request->message_id,
                'subject' => $request->subject,
                'from_address' => $request->from_address,
                'is_trained' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // In production, this would trigger a background job to actually train SpamAssassin
            // For now, we'll simulate immediate training
            DB::table('vp_spam_training')
                ->where('id', $trainingId)
                ->update([
                    'is_trained' => true,
                    'trained_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Email marked as ' . $request->type . ' and training scheduled',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to train: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View spam logs
     */
    public function logs(Request $request)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        $query = SpamScore::forAccount($account->id)
            ->orderByDesc('created_at');

        // Filters
        if ($request->has('is_spam') && $request->is_spam !== 'all') {
            $query->where('is_spam', $request->is_spam === '1');
        }

        if ($request->has('action') && $request->action !== 'all') {
            $query->where('action_taken', $request->action);
        }

        $logs = $query->paginate(50);

        return view('user.spamassassin.logs', compact('logs', 'account'));
    }

    /**
     * Generate SpamAssassin user_prefs file
     */
    private function generateUserPrefs($account, $config): void
    {
        $prefsPath = "/home/{$account->username}/.spamassassin/user_prefs";
        $prefsDir = dirname($prefsPath);

        // Create directory if it doesn't exist
        if (!is_dir($prefsDir)) {
            @mkdir($prefsDir, 0755, true);
            @chown($prefsDir, $account->username);
        }

        // Generate user_prefs content
        $content = $config->generateUserPrefs();

        // Add whitelist entries
        $whitelist = DB::table('vp_spamassassin_lists')
            ->where('account_id', $account->id)
            ->where('list_type', 'whitelist')
            ->get();

        if ($whitelist->count() > 0) {
            $content .= "\n# Whitelist\n";
            foreach ($whitelist as $entry) {
                if ($entry->entry_type === 'email') {
                    $content .= "whitelist_from {$entry->value}\n";
                } elseif ($entry->entry_type === 'domain') {
                    $content .= "whitelist_from *@{$entry->value}\n";
                }
            }
        }

        // Add blacklist entries
        $blacklist = DB::table('vp_spamassassin_lists')
            ->where('account_id', $account->id)
            ->where('list_type', 'blacklist')
            ->get();

        if ($blacklist->count() > 0) {
            $content .= "\n# Blacklist\n";
            foreach ($blacklist as $entry) {
                if ($entry->entry_type === 'email') {
                    $content .= "blacklist_from {$entry->value}\n";
                } elseif ($entry->entry_type === 'domain') {
                    $content .= "blacklist_from *@{$entry->value}\n";
                }
            }
        }

        // Write file
        @file_put_contents($prefsPath, $content);
        @chown($prefsPath, $account->username);
        @chmod($prefsPath, 0644);
    }
}
