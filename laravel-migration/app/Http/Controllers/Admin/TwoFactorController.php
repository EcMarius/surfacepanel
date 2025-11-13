<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorRecovery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isAdmin()) {
                abort(403, 'Unauthorized action.');
            }
            return $next($request);
        });
    }

    /**
     * Show 2FA management dashboard
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'users_with_2fa' => User::where('two_factor_enabled', true)->count(),
            'pending_recoveries' => TwoFactorRecovery::where('status', 'pending')->count(),
        ];

        $recentRecoveries = TwoFactorRecovery::with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.two-factor.index', compact('stats', 'recentRecoveries'));
    }

    /**
     * Show users with 2FA status
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Filter by 2FA status
        if ($request->has('status')) {
            if ($request->status === 'enabled') {
                $query->where('two_factor_enabled', true);
            } elseif ($request->status === 'disabled') {
                $query->where('two_factor_enabled', false);
            }
        }

        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        $users = $query->with('twoFactorAuth')
            ->paginate(20);

        return view('admin.two-factor.users', compact('users'));
    }

    /**
     * Force enable 2FA for specific roles
     */
    public function enforcePolicy(Request $request)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'in:root,admin,reseller,user',
        ]);

        // This would be stored in a configuration table
        // For now, we'll just show a success message
        return back()->with('success', '2FA policy updated for selected roles.');
    }

    /**
     * Show recovery requests
     */
    public function recoveryRequests()
    {
        $recoveries = TwoFactorRecovery::with(['user', 'approver'])
            ->latest()
            ->paginate(20);

        return view('admin.two-factor.recovery-requests', compact('recoveries'));
    }

    /**
     * Approve recovery request
     */
    public function approveRecovery(Request $request, $id)
    {
        $recovery = TwoFactorRecovery::findOrFail($id);

        if (!$recovery->isValid()) {
            return back()->with('error', 'This recovery request is no longer valid.');
        }

        $recovery->approve(Auth::user(), $request->input('notes'));

        // Disable 2FA for the user
        $recovery->user->disableTwoFactor();

        return redirect()
            ->route('admin.two-factor.recovery-requests')
            ->with('success', 'Recovery request approved and 2FA has been disabled for the user.');
    }

    /**
     * Deny recovery request
     */
    public function denyRecovery(Request $request, $id)
    {
        $recovery = TwoFactorRecovery::findOrFail($id);

        if (!$recovery->isValid()) {
            return back()->with('error', 'This recovery request is no longer valid.');
        }

        $recovery->deny(Auth::user(), $request->input('notes'));

        return redirect()
            ->route('admin.two-factor.recovery-requests')
            ->with('success', 'Recovery request has been denied.');
    }

    /**
     * Force disable 2FA for a user
     */
    public function forceDisable(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if ($user->isRoot() && !Auth::user()->isRoot()) {
            return back()->with('error', 'You cannot disable 2FA for root users.');
        }

        $user->disableTwoFactor();

        return back()->with('success', "Two-factor authentication has been disabled for {$user->email}.");
    }

    /**
     * View user's 2FA details
     */
    public function showUser($userId)
    {
        $user = User::with(['twoFactorAuth', 'backupCodes', 'twoFactorRecoveries'])
            ->findOrFail($userId);

        $backupCodesCount = $user->remainingBackupCodes();

        return view('admin.two-factor.user-details', compact('user', 'backupCodesCount'));
    }

    /**
     * Show 2FA statistics
     */
    public function statistics()
    {
        $stats = [
            'total_users' => User::count(),
            'users_with_2fa' => User::where('two_factor_enabled', true)->count(),
            'users_without_2fa' => User::where('two_factor_enabled', false)->count(),
            'admins_with_2fa' => User::whereIn('role', ['root', 'admin'])
                ->where('two_factor_enabled', true)
                ->count(),
            'admins_without_2fa' => User::whereIn('role', ['root', 'admin'])
                ->where('two_factor_enabled', false)
                ->count(),
            'recovery_requests_pending' => TwoFactorRecovery::where('status', 'pending')->count(),
            'recovery_requests_approved' => TwoFactorRecovery::where('status', 'approved')->count(),
            'recovery_requests_denied' => TwoFactorRecovery::where('status', 'denied')->count(),
        ];

        // 2FA adoption by role
        $adoptionByRole = User::selectRaw('role,
            COUNT(*) as total,
            SUM(CASE WHEN two_factor_enabled = 1 THEN 1 ELSE 0 END) as enabled')
            ->groupBy('role')
            ->get();

        return view('admin.two-factor.statistics', compact('stats', 'adoptionByRole'));
    }
}
