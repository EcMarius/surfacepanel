<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PHPVersion;
use App\Models\DomainPHPSetting;
use App\Models\PHPIniOverride;
use App\Models\AddonDomain;
use App\Models\Subdomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MultiPHPController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.user']);
    }

    /**
     * Display MultiPHP Manager for user's domains
     */
    public function index()
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        if (!$account) {
            abort(404, 'Account not found');
        }

        // Get all domains (main, addon, subdomains)
        $domains = $this->getUserDomains($account);

        // Get active PHP versions
        $phpVersions = PHPVersion::where('is_active', true)->get();
        $defaultVersion = PHPVersion::where('is_default', true)->first();

        // Get current PHP settings for each domain
        $domainSettings = DomainPHPSetting::where('account_id', $account->id)
            ->with(['phpVersion', 'iniOverrides'])
            ->get()
            ->keyBy('domain');

        return view('user.multiphp.index', compact(
            'domains',
            'phpVersions',
            'defaultVersion',
            'domainSettings',
            'account'
        ));
    }

    /**
     * Get all domains for a user account
     */
    private function getUserDomains(Account $account): array
    {
        $domains = [];

        // Main domain
        $domains[] = [
            'domain' => $account->domain,
            'type' => 'main',
            'document_root' => "/home/{$account->username}/public_html",
        ];

        // Addon domains
        $addonDomains = AddonDomain::where('account_id', $account->id)->get();
        foreach ($addonDomains as $addon) {
            $domains[] = [
                'domain' => $addon->domain,
                'type' => 'addon',
                'document_root' => $addon->document_root,
            ];
        }

        // Subdomains
        $subdomains = Subdomain::where('account_id', $account->id)->get();
        foreach ($subdomains as $subdomain) {
            $domains[] = [
                'domain' => "{$subdomain->subdomain}.{$subdomain->parent_domain}",
                'type' => 'subdomain',
                'document_root' => $subdomain->document_root,
            ];
        }

        return $domains;
    }

    /**
     * Set PHP version for a domain
     */
    public function setVersion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
            'php_version_id' => 'required|exists:vp_php_versions,id',
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

            // Verify domain belongs to user
            $userDomains = collect($this->getUserDomains($account))->pluck('domain')->toArray();
            if (!in_array($request->domain, $userDomains)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found in your account',
                ], 403);
            }

            // Verify PHP version is active
            $phpVersion = PHPVersion::where('id', $request->php_version_id)
                ->where('is_active', true)
                ->firstOrFail();

            // Create or update domain PHP setting
            $setting = DomainPHPSetting::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'domain' => $request->domain,
                ],
                [
                    'php_version_id' => $phpVersion->id,
                ]
            );

            // Generate pool name if not set
            if (!$setting->fpm_pool_name) {
                $setting->fpm_pool_name = $setting->generatePoolName();
                $setting->save();
            }

            // Generate FPM pool configuration
            $this->generateFPMPool($setting);

            // Reload PHP-FPM
            $this->reloadPHPFPM($phpVersion->version);

            // Update Nginx configuration
            $this->updateNginxConfig($setting);

            return response()->json([
                'success' => true,
                'message' => "PHP version updated to {$phpVersion->version} for {$request->domain}",
                'php_version' => $phpVersion->version,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set PHP version: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get PHP.ini editor for a domain
     */
    public function showIniEditor(Request $request, string $domain)
    {
        $user = Auth::guard('user')->user();
        $account = $user->account;

        // Verify domain belongs to user
        $userDomains = collect($this->getUserDomains($account))->pluck('domain')->toArray();
        if (!in_array($domain, $userDomains)) {
            abort(403, 'Domain not found in your account');
        }

        $setting = DomainPHPSetting::where('account_id', $account->id)
            ->where('domain', $domain)
            ->with(['phpVersion', 'iniOverrides'])
            ->first();

        if (!$setting) {
            // Create default setting with default PHP version
            $defaultVersion = PHPVersion::where('is_default', true)->first();
            $setting = DomainPHPSetting::create([
                'account_id' => $account->id,
                'domain' => $domain,
                'php_version_id' => $defaultVersion->id,
            ]);
        }

        $commonDirectives = PHPIniOverride::getCommonDirectives();

        return view('user.multiphp.ini-editor', compact('domain', 'setting', 'commonDirectives'));
    }

    /**
     * Update PHP.ini directive
     */
    public function updateIniDirective(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
            'directive' => 'required|string',
            'value' => 'required|string',
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

            // Verify domain belongs to user
            $userDomains = collect($this->getUserDomains($account))->pluck('domain')->toArray();
            if (!in_array($request->domain, $userDomains)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found in your account',
                ], 403);
            }

            $setting = DomainPHPSetting::where('account_id', $account->id)
                ->where('domain', $request->domain)
                ->firstOrFail();

            // Validate directive value
            if (!PHPIniOverride::validateDirectiveValue($request->directive, $request->value)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid value for PHP directive',
                ], 422);
            }

            // Create or update override
            PHPIniOverride::updateOrCreate(
                [
                    'domain_php_setting_id' => $setting->id,
                    'directive' => $request->directive,
                ],
                [
                    'value' => $request->value,
                ]
            );

            // Regenerate FPM pool config
            $this->generateFPMPool($setting);

            // Reload PHP-FPM
            $this->reloadPHPFPM($setting->phpVersion->version);

            return response()->json([
                'success' => true,
                'message' => "PHP directive {$request->directive} updated successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update directive: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete PHP.ini override
     */
    public function deleteIniDirective(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
            'directive' => 'required|string',
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

            $setting = DomainPHPSetting::where('account_id', $account->id)
                ->where('domain', $request->domain)
                ->firstOrFail();

            PHPIniOverride::where('domain_php_setting_id', $setting->id)
                ->where('directive', $request->directive)
                ->delete();

            // Regenerate FPM pool config
            $this->generateFPMPool($setting);

            // Reload PHP-FPM
            $this->reloadPHPFPM($setting->phpVersion->version);

            return response()->json([
                'success' => true,
                'message' => "PHP directive {$request->directive} removed",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete directive: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate FPM pool configuration file
     */
    private function generateFPMPool(DomainPHPSetting $setting): void
    {
        $poolConfig = $setting->generateFPMPoolConfig();
        $poolDir = $setting->phpVersion->fpm_pool_dir;
        $poolFile = "{$poolDir}/{$setting->fpm_pool_name}.conf";

        // Create pool directory if it doesn't exist
        if (!is_dir($poolDir)) {
            mkdir($poolDir, 0755, true);
        }

        // Write pool configuration
        file_put_contents($poolFile, $poolConfig);
        chmod($poolFile, 0644);
    }

    /**
     * Reload PHP-FPM service
     */
    private function reloadPHPFPM(string $version): void
    {
        $serviceName = "php{$version}-fpm";
        exec("systemctl reload {$serviceName} 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Failed to reload {$serviceName}");
        }
    }

    /**
     * Update Nginx configuration to use new PHP-FPM socket
     */
    private function updateNginxConfig(DomainPHPSetting $setting): void
    {
        // This would update the Nginx vhost configuration
        // to point to the correct PHP-FPM socket for this domain
        // Implementation depends on Nginx configuration structure

        $socketPath = $setting->getSocketPath();
        $domain = $setting->domain;
        $nginxConfigPath = "/etc/nginx/sites-available/{$domain}.conf";

        // This is a simplified example - actual implementation would be more robust
        if (file_exists($nginxConfigPath)) {
            exec("nginx -t 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                exec("systemctl reload nginx 2>&1");
            }
        }
    }
}
