<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AdminLoginController;

/*
|--------------------------------------------------------------------------
| WHM Admin Routes (Port 15443)
|--------------------------------------------------------------------------
|
| These routes are for the WHM admin panel accessible on port 15443
| Requires 'admin' guard authentication
|
*/

// Admin Authentication Routes
Route::prefix('admin')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/login', [AdminLoginController::class, 'login']);
    });

    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('admin.logout');
});

// Protected Admin Routes
Route::prefix('admin')->middleware(['panel.detector', 'auth.admin'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('admin.dashboard');

    // Account Management
    Route::resource('accounts', Admin\AccountController::class);
    Route::post('accounts/{id}/suspend', [Admin\AccountController::class, 'suspend'])->name('admin.accounts.suspend');
    Route::post('accounts/{id}/unsuspend', [Admin\AccountController::class, 'unsuspend'])->name('admin.accounts.unsuspend');
    Route::post('accounts/{id}/terminate', [Admin\AccountController::class, 'terminate'])->name('admin.accounts.terminate');

    // Package Management
    Route::resource('packages', Admin\PackageController::class);

    // Reseller Management
    Route::resource('resellers', Admin\ResellerController::class);
    Route::post('resellers/{id}/suspend', [Admin\ResellerController::class, 'suspend'])->name('admin.resellers.suspend');
    Route::post('resellers/{id}/unsuspend', [Admin\ResellerController::class, 'unsuspend'])->name('admin.resellers.unsuspend');

    // IP Address Management
    Route::resource('ip-addresses', Admin\IPAddressController::class);
    Route::post('ip-addresses/{id}/set-default', [Admin\IPAddressController::class, 'setDefault'])->name('admin.ip-addresses.set-default');
    Route::post('ip-addresses/assign', [Admin\IPAddressController::class, 'assignToAccount'])->name('admin.ip-addresses.assign');

    // DNS Management
    Route::get('dns', [Admin\DNSController::class, 'index'])->name('admin.dns.index');
    Route::resource('dns-zones', Admin\DNSZoneController::class);

    // SSL Certificate Management
    Route::get('ssl', [Admin\SSLController::class, 'index'])->name('admin.ssl.index');
    Route::post('ssl/letsencrypt/auto', [Admin\SSLController::class, 'autoInstallLetsEncrypt'])->name('admin.ssl.letsencrypt.auto');

    // Server Configuration
    Route::get('server/settings', [Admin\ServerController::class, 'settings'])->name('admin.server.settings');
    Route::post('server/settings', [Admin\ServerController::class, 'updateSettings']);
    Route::get('server/services', [Admin\ServerController::class, 'services'])->name('admin.server.services');
    Route::post('server/services/{service}/restart', [Admin\ServerController::class, 'restartService'])->name('admin.server.services.restart');

    // Backup Management
    Route::get('backups', [Admin\BackupController::class, 'index'])->name('admin.backups.index');
    Route::post('backups/create', [Admin\BackupController::class, 'create'])->name('admin.backups.create');

    // Module Management
    Route::resource('modules', Admin\ModuleController::class);
    Route::post('modules/{name}/enable', [Admin\ModuleController::class, 'enable'])->name('admin.modules.enable');
    Route::post('modules/{name}/disable', [Admin\ModuleController::class, 'disable'])->name('admin.modules.disable');

    // Template Management
    Route::resource('templates', Admin\TemplateController::class);
    Route::post('templates/{name}/activate', [Admin\TemplateController::class, 'activate'])->name('admin.templates.activate');

    // API Token Management
    Route::resource('api-tokens', Admin\ApiTokenController::class);

    // Audit Logs
    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('admin.audit-logs.index');

    // System Statistics
    Route::get('statistics', [Admin\StatisticsController::class, 'index'])->name('admin.statistics.index');

    // MultiPHP Manager (System-wide PHP version management)
    Route::get('multiphp', [Admin\MultiPHPController::class, 'index'])->name('admin.multiphp.index');
    Route::post('multiphp/detect', [Admin\MultiPHPController::class, 'detectVersions'])->name('admin.multiphp.detect');
    Route::post('multiphp', [Admin\MultiPHPController::class, 'store'])->name('admin.multiphp.store');
    Route::get('multiphp/{id}', [Admin\MultiPHPController::class, 'show'])->name('admin.multiphp.show');
    Route::post('multiphp/{id}/set-default', [Admin\MultiPHPController::class, 'setDefault'])->name('admin.multiphp.set-default');
    Route::post('multiphp/{id}/toggle-active', [Admin\MultiPHPController::class, 'toggleActive'])->name('admin.multiphp.toggle-active');
    Route::post('multiphp/{id}/refresh-extensions', [Admin\MultiPHPController::class, 'refreshExtensions'])->name('admin.multiphp.refresh-extensions');
    Route::delete('multiphp/{id}', [Admin\MultiPHPController::class, 'destroy'])->name('admin.multiphp.destroy');

    // Web Application Firewall (ModSecurity/WAF)
    Route::get('waf', [Admin\WAFController::class, 'index'])->name('admin.waf.index');
    Route::post('waf/config', [Admin\WAFController::class, 'updateConfig'])->name('admin.waf.config');
    Route::get('waf/logs', [Admin\WAFController::class, 'logs'])->name('admin.waf.logs');
    Route::get('waf/statistics', [Admin\WAFController::class, 'statistics'])->name('admin.waf.statistics');
    Route::get('waf/whitelist', [Admin\WAFController::class, 'whitelist'])->name('admin.waf.whitelist');
    Route::post('waf/whitelist', [Admin\WAFController::class, 'addWhitelist'])->name('admin.waf.whitelist.add');
    Route::delete('waf/whitelist/{id}', [Admin\WAFController::class, 'removeWhitelist'])->name('admin.waf.whitelist.remove');
    Route::get('waf/blacklist', [Admin\WAFController::class, 'blacklist'])->name('admin.waf.blacklist');
    Route::post('waf/blacklist', [Admin\WAFController::class, 'addBlacklist'])->name('admin.waf.blacklist.add');
    Route::delete('waf/blacklist/{id}', [Admin\WAFController::class, 'removeBlacklist'])->name('admin.waf.blacklist.remove');

    // CSF/Firewall (ConfigServer Security & Firewall)
    Route::get('firewall', [Admin\FirewallController::class, 'index'])->name('admin.firewall.index');
    Route::post('firewall/config', [Admin\FirewallController::class, 'updateConfig'])->name('admin.firewall.config');
    Route::post('firewall/block-ip', [Admin\FirewallController::class, 'blockIP'])->name('admin.firewall.block-ip');
    Route::post('firewall/unblock-ip', [Admin\FirewallController::class, 'unblockIP'])->name('admin.firewall.unblock-ip');
    Route::get('firewall/blocked-ips', [Admin\FirewallController::class, 'blockedIPs'])->name('admin.firewall.blocked-ips');
    Route::get('firewall/login-failures', [Admin\FirewallController::class, 'loginFailures'])->name('admin.firewall.login-failures');
    Route::post('firewall/cleanup', [Admin\FirewallController::class, 'cleanup'])->name('admin.firewall.cleanup');
    Route::get('firewall/statistics', [Admin\FirewallController::class, 'statistics'])->name('admin.firewall.statistics');

    // OVH Cloud Provider Integration
    Route::get('ovh', [Admin\OVHController::class, 'index'])->name('admin.ovh.index');
    Route::get('ovh/config', [Admin\OVHController::class, 'showConfig'])->name('admin.ovh.config');
    Route::post('ovh/config', [Admin\OVHController::class, 'updateConfig'])->name('admin.ovh.update-config');
    Route::post('ovh/request-consumer-key', [Admin\OVHController::class, 'requestConsumerKey'])->name('admin.ovh.request-consumer-key');
    Route::post('ovh/test-connection', [Admin\OVHController::class, 'testConnection'])->name('admin.ovh.test-connection');
    Route::post('ovh/toggle-active', [Admin\OVHController::class, 'toggleActive'])->name('admin.ovh.toggle-active');
    Route::get('ovh/servers', [Admin\OVHController::class, 'servers'])->name('admin.ovh.servers');
    Route::post('ovh/sync-servers', [Admin\OVHController::class, 'syncServers'])->name('admin.ovh.sync-servers');
    Route::get('ovh/provision', [Admin\OVHController::class, 'showProvision'])->name('admin.ovh.provision');
    Route::post('ovh/provision-server', [Admin\OVHController::class, 'provisionServer'])->name('admin.ovh.provision-server');
    Route::get('ovh/servers/{id}/details', [Admin\OVHController::class, 'getServerDetails'])->name('admin.ovh.server-details');
    Route::post('ovh/servers/{id}/reboot', [Admin\OVHController::class, 'rebootServer'])->name('admin.ovh.server-reboot');
    Route::post('ovh/servers/{id}/reinstall', [Admin\OVHController::class, 'reinstallServer'])->name('admin.ovh.server-reinstall');
    Route::get('ovh/failover-ips', [Admin\OVHController::class, 'failoverIps'])->name('admin.ovh.failover-ips');
    Route::post('ovh/route-failover-ip', [Admin\OVHController::class, 'routeFailoverIp'])->name('admin.ovh.route-failover-ip');
    Route::post('ovh/update-reverse-dns', [Admin\OVHController::class, 'updateReverseDns'])->name('admin.ovh.update-reverse-dns');
    Route::get('ovh/billing', [Admin\OVHController::class, 'billing'])->name('admin.ovh.billing');
    Route::post('ovh/sync-billing', [Admin\OVHController::class, 'syncBilling'])->name('admin.ovh.sync-billing');
    Route::get('ovh/cloud-instances', [Admin\OVHController::class, 'cloudInstances'])->name('admin.ovh.cloud-instances');
    Route::post('ovh/cloud-instances', [Admin\OVHController::class, 'createCloudInstance'])->name('admin.ovh.create-cloud-instance');
    Route::post('ovh/cloud-instances/{id}/control', [Admin\OVHController::class, 'controlCloudInstance'])->name('admin.ovh.control-cloud-instance');
    Route::get('ovh/servers/{id}/network-stats', [Admin\OVHController::class, 'networkStats'])->name('admin.ovh.network-stats');

    // SpamAssassin (Email spam filtering)
    Route::get('spamassassin', [Admin\SpamAssassinController::class, 'index'])->name('admin.spamassassin.index');
    Route::get('spamassassin/settings', [Admin\SpamAssassinController::class, 'settings'])->name('admin.spamassassin.settings');
    Route::post('spamassassin/settings', [Admin\SpamAssassinController::class, 'updateSettings'])->name('admin.spamassassin.settings.update');
    Route::post('spamassassin/enable-account', [Admin\SpamAssassinController::class, 'enableAccount'])->name('admin.spamassassin.enable-account');
    Route::post('spamassassin/disable-account', [Admin\SpamAssassinController::class, 'disableAccount'])->name('admin.spamassassin.disable-account');
    Route::get('spamassassin/logs', [Admin\SpamAssassinController::class, 'logs'])->name('admin.spamassassin.logs');
    Route::get('spamassassin/statistics', [Admin\SpamAssassinController::class, 'statistics'])->name('admin.spamassassin.statistics');
    Route::post('spamassassin/rebuild-bayes', [Admin\SpamAssassinController::class, 'rebuildBayes'])->name('admin.spamassassin.rebuild-bayes');
    Route::post('spamassassin/update-rules', [Admin\SpamAssassinController::class, 'updateRules'])->name('admin.spamassassin.update-rules');

    // Webmail Management (Roundcube)
    Route::get('webmail', [Admin\WebmailController::class, 'index'])->name('admin.webmail.index');
    Route::get('webmail/install', [Admin\WebmailController::class, 'showInstallation'])->name('admin.webmail.install');
    Route::post('webmail/install', [Admin\WebmailController::class, 'install']);
    Route::post('webmail/config', [Admin\WebmailController::class, 'updateConfig'])->name('admin.webmail.config');
    Route::post('webmail/plugins', [Admin\WebmailController::class, 'updatePlugins'])->name('admin.webmail.plugins');
    Route::post('webmail/uninstall', [Admin\WebmailController::class, 'uninstall'])->name('admin.webmail.uninstall');
    Route::get('webmail/sessions', [Admin\WebmailController::class, 'sessions'])->name('admin.webmail.sessions');
    Route::get('webmail/statistics', [Admin\WebmailController::class, 'statistics'])->name('admin.webmail.statistics');

    // Two-Factor Authentication Management
    Route::get('two-factor', [Admin\TwoFactorController::class, 'index'])->name('admin.two-factor.index');
    Route::get('two-factor/users', [Admin\TwoFactorController::class, 'users'])->name('admin.two-factor.users');
    Route::post('two-factor/enforce-policy', [Admin\TwoFactorController::class, 'enforcePolicy'])->name('admin.two-factor.enforce-policy');
    Route::get('two-factor/recovery-requests', [Admin\TwoFactorController::class, 'recoveryRequests'])->name('admin.two-factor.recovery-requests');
    Route::post('two-factor/recovery-requests/{id}/approve', [Admin\TwoFactorController::class, 'approveRecovery'])->name('admin.two-factor.recovery.approve');
    Route::post('two-factor/recovery-requests/{id}/deny', [Admin\TwoFactorController::class, 'denyRecovery'])->name('admin.two-factor.recovery.deny');
    Route::delete('two-factor/users/{userId}/force-disable', [Admin\TwoFactorController::class, 'forceDisable'])->name('admin.two-factor.force-disable');
    Route::get('two-factor/users/{userId}/details', [Admin\TwoFactorController::class, 'showUser'])->name('admin.two-factor.user-details');
    Route::get('two-factor/statistics', [Admin\TwoFactorController::class, 'statistics'])->name('admin.two-factor.statistics');

    // cPanel/WHM Migration
    Route::get('migration', [Admin\MigrationController::class, 'index'])->name('admin.migration.index');
    Route::get('migration/create', [Admin\MigrationController::class, 'create'])->name('admin.migration.create');
    Route::post('migration', [Admin\MigrationController::class, 'store'])->name('admin.migration.store');
    Route::get('migration/{id}', [Admin\MigrationController::class, 'show'])->name('admin.migration.show');
    Route::post('migration/{id}/start', [Admin\MigrationController::class, 'start'])->name('admin.migration.start');
    Route::get('migration/{id}/progress', [Admin\MigrationController::class, 'progress'])->name('admin.migration.progress');
    Route::get('migration/{id}/status', [Admin\MigrationController::class, 'status'])->name('admin.migration.status');
    Route::get('migration/{id}/report', [Admin\MigrationController::class, 'report'])->name('admin.migration.report');
    Route::post('migration/{id}/rollback', [Admin\MigrationController::class, 'rollback'])->name('admin.migration.rollback');
    Route::delete('migration/{id}', [Admin\MigrationController::class, 'destroy'])->name('admin.migration.destroy');
    Route::get('migration/{id}/download-logs', [Admin\MigrationController::class, 'downloadLogs'])->name('admin.migration.download-logs');
    Route::post('migration/{id}/validate', [Admin\MigrationController::class, 'validate'])->name('admin.migration.validate');
    Route::get('migration-statistics', [Admin\MigrationController::class, 'statistics'])->name('admin.migration.statistics');

    // GPU Server Management (NVIDIA GPU support)
    Route::get('gpu', [Admin\GPUController::class, 'index'])->name('admin.gpu.index');
    Route::post('gpu/detect', [Admin\GPUController::class, 'detectGPUs'])->name('admin.gpu.detect');
    Route::post('gpu/{id}/refresh', [Admin\GPUController::class, 'refreshStats'])->name('admin.gpu.refresh');
    Route::get('gpu/allocations', [Admin\GPUController::class, 'allocations'])->name('admin.gpu.allocations');
    Route::post('gpu/allocate', [Admin\GPUController::class, 'allocateGPU'])->name('admin.gpu.allocate');
    Route::post('gpu/allocation/{id}/deallocate', [Admin\GPUController::class, 'deallocateGPU'])->name('admin.gpu.deallocate');
    Route::post('gpu/install-cuda', [Admin\GPUController::class, 'installCUDA'])->name('admin.gpu.install-cuda');
    Route::post('gpu/install-cudnn', [Admin\GPUController::class, 'installCuDNN'])->name('admin.gpu.install-cudnn');
    Route::get('gpu/frameworks', [Admin\GPUController::class, 'frameworks'])->name('admin.gpu.frameworks');
    Route::post('gpu/frameworks', [Admin\GPUController::class, 'addFramework'])->name('admin.gpu.frameworks.add');
    Route::get('gpu/monitoring', [Admin\GPUController::class, 'monitoring'])->name('admin.gpu.monitoring');
    Route::get('gpu/monitoring/data', [Admin\GPUController::class, 'getMonitoringData'])->name('admin.gpu.monitoring.data');
});

// Redirect /admin to dashboard
Route::get('/admin', fn() => redirect()->route('admin.dashboard'))->middleware(['panel.detector', 'auth.admin']);
