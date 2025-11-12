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
});

// Redirect /admin to dashboard
Route::get('/admin', fn() => redirect()->route('admin.dashboard'))->middleware(['panel.detector', 'auth.admin']);
