<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BackupCode;
use App\Models\TwoFactorAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->middleware('auth');
        $this->google2fa = new Google2FA();
    }

    /**
     * Show 2FA settings page
     */
    public function index()
    {
        $user = Auth::user();
        $twoFactorAuth = $user->twoFactorAuth;
        $backupCodesCount = $user->remainingBackupCodes();

        return view('user.settings.two-factor', compact('user', 'twoFactorAuth', 'backupCodesCount'));
    }

    /**
     * Show QR code for 2FA setup
     */
    public function setup()
    {
        $user = Auth::user();

        // Generate a new secret if user doesn't have one
        if (!$user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->setTwoFactorSecret($secret);
            $user->save();
        } else {
            $secret = $user->getTwoFactorSecret();
        }

        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'VirPanel'),
            $user->email,
            $secret
        );

        return view('user.settings.two-factor-setup', compact('secret', 'qrCodeUrl'));
    }

    /**
     * Enable 2FA after verification
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $secret = $user->getTwoFactorSecret();

        if (!$secret) {
            return back()->with('error', 'Please setup 2FA first.');
        }

        // Verify the code
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return back()->with('error', 'Invalid verification code. Please try again.');
        }

        // Enable 2FA
        $user->enableTwoFactor();

        // Create or update 2FA settings
        TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'type' => 'totp',
                'is_enabled' => true,
                'secret' => encrypt($secret),
                'enabled_at' => now(),
            ]
        );

        // Generate backup codes
        $backupCodes = BackupCode::generateForUser($user);

        return redirect()
            ->route('user.two-factor.backup-codes')
            ->with('success', 'Two-factor authentication has been enabled.')
            ->with('backup_codes', $backupCodes);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        $user = Auth::user();

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Invalid password.');
        }

        // Disable 2FA
        $user->disableTwoFactor();

        return redirect()
            ->route('user.two-factor.index')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Show backup codes
     */
    public function showBackupCodes()
    {
        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('user.two-factor.index')
                ->with('error', 'Two-factor authentication is not enabled.');
        }

        $backupCodes = session('backup_codes', []);
        $backupCodesCount = $user->remainingBackupCodes();

        return view('user.settings.backup-codes', compact('backupCodes', 'backupCodesCount'));
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return back()->with('error', 'Two-factor authentication is not enabled.');
        }

        // Verify password
        if (!\Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Invalid password.');
        }

        // Generate new backup codes
        $backupCodes = BackupCode::generateForUser($user);

        return back()
            ->with('success', 'Backup codes have been regenerated.')
            ->with('backup_codes', $backupCodes);
    }

    /**
     * Show 2FA challenge page
     */
    public function challenge()
    {
        $user = Auth::user();

        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        // If already verified in this session
        if (session('two_factor_verified', false)) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify 2FA code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$user || !$user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        $code = $request->code;
        $secret = $user->getTwoFactorSecret();

        // Try TOTP verification first
        $valid = $this->google2fa->verifyKey($secret, $code, 2); // Allow 2 time steps tolerance

        if ($valid) {
            // Mark session as 2FA verified
            $request->session()->put('two_factor_verified', true);

            // Update last used
            if ($twoFactorAuth = $user->twoFactorAuth) {
                $twoFactorAuth->recordUsage($request->ip());
            }

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Two-factor authentication verified.');
        }

        // If TOTP fails, try backup code
        $backupCodeValid = BackupCode::verifyForUser($user, $code, $request->ip());

        if ($backupCodeValid) {
            // Mark session as 2FA verified
            $request->session()->put('two_factor_verified', true);

            $remainingCodes = $user->remainingBackupCodes();

            if ($remainingCodes === 0) {
                return redirect()->intended(route('dashboard'))
                    ->with('warning', 'Backup code accepted. You have no remaining backup codes. Please generate new ones.');
            }

            return redirect()->intended(route('dashboard'))
                ->with('success', "Backup code accepted. You have {$remainingCodes} backup codes remaining.");
        }

        return back()
            ->withErrors(['code' => 'Invalid authentication code.'])
            ->withInput();
    }

    /**
     * Request 2FA recovery
     */
    public function requestRecovery(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // This would typically send a recovery request to admins
        // For now, we'll just show a message
        return back()->with('info', 'A recovery request has been submitted. An administrator will review it shortly.');
    }
}
