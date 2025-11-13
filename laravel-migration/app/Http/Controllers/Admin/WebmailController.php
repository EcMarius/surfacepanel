<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebmailConfig;
use App\Models\WebmailSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class WebmailController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
    }

    /**
     * Display webmail management dashboard
     */
    public function index()
    {
        $config = WebmailConfig::getInstance();
        $statistics = WebmailSession::getStatistics(30);

        return view('admin.webmail.index', compact('config', 'statistics'));
    }

    /**
     * Show installation wizard
     */
    public function showInstallation()
    {
        $config = WebmailConfig::getInstance();

        if ($config->isInstalled()) {
            return redirect()->route('admin.webmail.index')
                ->with('info', 'Roundcube is already installed');
        }

        return view('admin.webmail.install', compact('config'));
    }

    /**
     * Install Roundcube
     */
    public function install(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'installation_path' => 'required|string',
            'database_name' => 'required|string|max:64',
            'imap_host' => 'required|string',
            'smtp_host' => 'required|string',
            'product_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = WebmailConfig::getInstance();

            // Generate database credentials
            $dbUser = 'rc_' . substr(md5(time()), 0, 8);
            $dbPassword = WebmailConfig::generateDatabasePassword();
            $desKey = WebmailConfig::generateDesKey();

            // Update configuration
            $config->update([
                'installation_path' => $request->installation_path,
                'database_name' => $request->database_name,
                'database_user' => $dbUser,
                'database_password' => $dbPassword,
                'des_key' => $desKey,
                'imap_host' => $request->imap_host,
                'smtp_host' => $request->smtp_host,
                'product_name' => $request->product_name ?? 'VirPanel Webmail',
            ]);

            // Execute installation script
            $output = [];
            $returnCode = 0;

            $scriptPath = base_path('scripts/install-roundcube.sh');
            $command = "bash {$scriptPath} " . escapeshellarg($config->installation_path) . " " .
                      escapeshellarg($config->database_name) . " " .
                      escapeshellarg($dbUser) . " " .
                      escapeshellarg($dbPassword) . " " .
                      escapeshellarg($desKey);

            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                // Get installed version
                $version = $this->detectRoundcubeVersion($config->installation_path);

                $config->update([
                    'is_installed' => true,
                    'version' => $version,
                ]);

                // Generate Roundcube config
                $this->generateRoundcubeConfig($config);

                return response()->json([
                    'success' => true,
                    'message' => 'Roundcube installed successfully',
                    'version' => $version,
                    'output' => implode("\n", $output),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Installation failed',
                    'output' => implode("\n", $output),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Installation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update webmail configuration
     */
    public function updateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imap_host' => 'required|string',
            'smtp_host' => 'required|string',
            'smtp_auth' => 'required|boolean',
            'default_theme' => 'required|string',
            'product_name' => 'required|string|max:100',
            'force_https' => 'required|boolean',
            'session_lifetime' => 'required|integer|min:5|max:1440',
            'enabled_plugins' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = WebmailConfig::getInstance();

            if (!$config->isInstalled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Roundcube is not installed',
                ], 400);
            }

            $config->update($request->only([
                'imap_host',
                'smtp_host',
                'smtp_auth',
                'default_theme',
                'product_name',
                'force_https',
                'session_lifetime',
                'enabled_plugins',
            ]));

            // Regenerate Roundcube config
            $this->generateRoundcubeConfig($config);

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manage plugins
     */
    public function updatePlugins(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plugins' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $config = WebmailConfig::getInstance();

            if (!$config->isInstalled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Roundcube is not installed',
                ], 400);
            }

            $config->update([
                'enabled_plugins' => $request->plugins,
            ]);

            // Regenerate Roundcube config
            $this->generateRoundcubeConfig($config);

            return response()->json([
                'success' => true,
                'message' => 'Plugins updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plugins: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Uninstall Roundcube
     */
    public function uninstall(Request $request)
    {
        try {
            $config = WebmailConfig::getInstance();

            if (!$config->isInstalled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Roundcube is not installed',
                ], 400);
            }

            // Delete installation directory
            if (file_exists($config->installation_path)) {
                exec('rm -rf ' . escapeshellarg($config->installation_path), $output, $returnCode);
            }

            // Drop database
            if ($config->database_name) {
                DB::statement("DROP DATABASE IF EXISTS `{$config->database_name}`");
            }

            // Drop database user
            if ($config->database_user) {
                DB::statement("DROP USER IF EXISTS '{$config->database_user}'@'localhost'");
                DB::statement("FLUSH PRIVILEGES");
            }

            // Clear all SSO sessions
            WebmailSession::truncate();

            // Reset configuration
            $config->update([
                'is_installed' => false,
                'version' => null,
                'database_user' => null,
                'database_password' => null,
                'des_key' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Roundcube uninstalled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to uninstall: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View SSO sessions
     */
    public function sessions()
    {
        $sessions = WebmailSession::with(['user', 'account'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.webmail.sessions', compact('sessions'));
    }

    /**
     * Get webmail statistics
     */
    public function statistics(Request $request)
    {
        $days = $request->input('days', 30);
        $statistics = WebmailSession::getStatistics($days);

        return response()->json([
            'success' => true,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Generate Roundcube configuration file
     */
    private function generateRoundcubeConfig(WebmailConfig $config): void
    {
        $configPath = $config->installation_path . '/config/config.inc.php';

        // Get database credentials from environment or config
        $dbHost = env('DB_HOST', 'localhost');
        $dbPort = env('DB_PORT', 3306);

        $plugins = $config->enabled_plugins ?? [];
        $pluginsStr = "'" . implode("', '", $plugins) . "'";

        $content = "<?php\n\n";
        $content .= "/* VirPanel Roundcube Configuration */\n";
        $content .= "/* Generated: " . date('Y-m-d H:i:s') . " */\n\n";

        // Database configuration
        $content .= "\$config['db_dsnw'] = 'mysql://{$config->database_user}:{$config->database_password}@{$dbHost}:{$dbPort}/{$config->database_name}';\n\n";

        // IMAP/SMTP configuration
        $content .= "\$config['imap_host'] = '{$config->imap_host}';\n";
        $content .= "\$config['smtp_host'] = '{$config->smtp_host}';\n";
        $content .= "\$config['smtp_user'] = '%u';\n";
        $content .= "\$config['smtp_pass'] = '%p';\n";
        $content .= "\$config['smtp_auth_type'] = " . ($config->smtp_auth ? "'LOGIN'" : "''") . ";\n\n";

        // Security
        $content .= "\$config['des_key'] = '{$config->des_key}';\n";
        $content .= "\$config['force_https'] = " . ($config->force_https ? 'true' : 'false') . ";\n";
        $content .= "\$config['session_lifetime'] = {$config->session_lifetime};\n\n";

        // Branding
        $content .= "\$config['product_name'] = '{$config->product_name}';\n";
        $content .= "\$config['support_url'] = '';\n";
        $content .= "\$config['skin'] = '{$config->default_theme}';\n\n";

        // Plugins
        $content .= "\$config['plugins'] = [{$pluginsStr}];\n\n";

        // Additional settings
        $content .= "\$config['enable_installer'] = false;\n";
        $content .= "\$config['log_driver'] = 'syslog';\n";
        $content .= "\$config['log_logins'] = true;\n";
        $content .= "\$config['log_session'] = true;\n";
        $content .= "\$config['sql_debug'] = false;\n";
        $content .= "\$config['imap_debug'] = false;\n";
        $content .= "\$config['smtp_debug'] = false;\n\n";

        // SSO support
        $content .= "// VirPanel SSO Support\n";
        $content .= "\$config['auto_login'] = true;\n";
        $content .= "\$config['auto_login_user'] = \$_SESSION['virpanel_email'] ?? null;\n";
        $content .= "\$config['auto_login_pass'] = \$_SESSION['virpanel_password'] ?? null;\n";

        @file_put_contents($configPath, $content);
        @chmod($configPath, 0644);
    }

    /**
     * Detect installed Roundcube version
     */
    private function detectRoundcubeVersion(string $path): ?string
    {
        $versionFile = $path . '/index.php';

        if (file_exists($versionFile)) {
            $content = file_get_contents($versionFile);
            if (preg_match('/RCMAIL_VERSION.*?["\']([0-9.]+)["\']/', $content, $matches)) {
                return $matches[1];
            }
        }

        return 'unknown';
    }
}
