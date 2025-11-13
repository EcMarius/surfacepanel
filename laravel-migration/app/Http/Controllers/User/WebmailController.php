<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WebmailConfig;
use App\Models\WebmailSession;
use App\Models\Account;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebmailController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.user']);
    }

    /**
     * Display webmail access page
     */
    public function index()
    {
        $config = WebmailConfig::getInstance();

        if (!$config->isInstalled()) {
            return view('user.webmail.unavailable', [
                'message' => 'Webmail is not currently installed. Please contact your administrator.',
            ]);
        }

        $user = Auth::guard('user')->user();
        $account = Account::where('user_id', $user->id)->first();

        if (!$account) {
            return view('user.webmail.unavailable', [
                'message' => 'No account found. Please contact your administrator.',
            ]);
        }

        // Get all email accounts for this user
        $emailAccounts = EmailAccount::where('account_id', $account->id)
            ->where('status', 'active')
            ->orderBy('email')
            ->get();

        return view('user.webmail.index', compact('config', 'emailAccounts'));
    }

    /**
     * Generate SSO token and redirect to webmail
     */
    public function login(Request $request)
    {
        $config = WebmailConfig::getInstance();

        if (!$config->isInstalled()) {
            return response()->json([
                'success' => false,
                'message' => 'Webmail is not installed',
            ], 400);
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $user = Auth::guard('user')->user();
            $account = Account::where('user_id', $user->id)->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found',
                ], 404);
            }

            // Verify email belongs to this account
            $emailAccount = EmailAccount::where('account_id', $account->id)
                ->where('email', $request->email)
                ->where('status', 'active')
                ->first();

            if (!$emailAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email account not found or inactive',
                ], 404);
            }

            // Create SSO token
            $session = WebmailSession::createToken($user->id, $account->id, $request->email);

            // Build webmail URL with SSO token
            $webmailUrl = $config->getWebmailUrl() . '?sso=' . $session->token;

            return response()->json([
                'success' => true,
                'url' => $webmailUrl,
                'token' => $session->token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate login token: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Direct login with specific email (for quick access)
     */
    public function quickLogin(Request $request, string $email)
    {
        $config = WebmailConfig::getInstance();

        if (!$config->isInstalled()) {
            return redirect()->back()->with('error', 'Webmail is not installed');
        }

        try {
            $user = Auth::guard('user')->user();
            $account = Account::where('user_id', $user->id)->first();

            if (!$account) {
                return redirect()->back()->with('error', 'Account not found');
            }

            // Verify email belongs to this account
            $emailAccount = EmailAccount::where('account_id', $account->id)
                ->where('email', $email)
                ->where('status', 'active')
                ->first();

            if (!$emailAccount) {
                return redirect()->back()->with('error', 'Email account not found or inactive');
            }

            // Create SSO token
            $session = WebmailSession::createToken($user->id, $account->id, $email);

            // Redirect to webmail with SSO token
            $webmailUrl = $config->getWebmailUrl() . '?sso=' . $session->token;

            return redirect($webmailUrl);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to login: ' . $e->getMessage());
        }
    }

    /**
     * Validate SSO token (called by Roundcube plugin)
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $session = WebmailSession::validateToken($request->token);

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token',
                ], 401);
            }

            // Get email account details
            $emailAccount = EmailAccount::where('account_id', $session->account_id)
                ->where('email', $session->email)
                ->where('status', 'active')
                ->first();

            if (!$emailAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email account not found',
                ], 404);
            }

            // Return credentials for auto-login
            return response()->json([
                'success' => true,
                'email' => $emailAccount->email,
                'password' => decrypt($emailAccount->password), // Assuming passwords are encrypted
                'user_id' => $session->user_id,
                'account_id' => $session->account_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token validation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's email accounts for webmail access
     */
    public function getEmailAccounts()
    {
        try {
            $user = Auth::guard('user')->user();
            $account = Account::where('user_id', $user->id)->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found',
                ], 404);
            }

            $emailAccounts = EmailAccount::where('account_id', $account->id)
                ->where('status', 'active')
                ->select('id', 'email', 'quota', 'used')
                ->orderBy('email')
                ->get();

            return response()->json([
                'success' => true,
                'accounts' => $emailAccounts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accounts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create default email account (if none exists)
     */
    public function createDefaultEmail(Request $request)
    {
        try {
            $user = Auth::guard('user')->user();
            $account = Account::where('user_id', $user->id)->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found',
                ], 404);
            }

            // Check if default email already exists
            $defaultEmail = $user->email;
            $existingEmail = EmailAccount::where('account_id', $account->id)
                ->where('email', $defaultEmail)
                ->first();

            if ($existingEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email account already exists',
                ], 400);
            }

            // Create default email account
            $password = $request->input('password', bin2hex(random_bytes(8)));

            $emailAccount = EmailAccount::create([
                'account_id' => $account->id,
                'email' => $defaultEmail,
                'password' => encrypt($password),
                'quota' => 250, // 250 MB default
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email account created successfully',
                'email' => $emailAccount->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke all active sessions for current user
     */
    public function revokeSessions()
    {
        try {
            $user = Auth::guard('user')->user();
            $count = WebmailSession::revokeAllForUser($user->id);

            return response()->json([
                'success' => true,
                'message' => "Revoked {$count} active session(s)",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke sessions: ' . $e->getMessage(),
            ], 500);
        }
    }
}
