<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User;
use App\Http\Controllers\Auth\UserLoginController;

/*
|--------------------------------------------------------------------------
| User Panel Routes (Port 15444)
|--------------------------------------------------------------------------
|
| These routes are for the user panel (cPanel equivalent) on port 15444
| Requires 'user' guard authentication
|
*/

// User Authentication Routes
Route::middleware('guest:user')->group(function () {
    Route::get('/login', [UserLoginController::class, 'showLoginForm'])->name('user.login');
    Route::post('/login', [UserLoginController::class, 'login']);
});

Route::post('/logout', [UserLoginController::class, 'logout'])->name('user.logout');

// Two-Factor Authentication Routes (accessible after basic auth)
Route::middleware(['panel.detector', 'auth.user'])->group(function () {
    Route::get('/two-factor/challenge', [User\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/verify', [User\TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/two-factor/recovery-request', [User\TwoFactorController::class, 'requestRecovery'])->name('two-factor.recovery-request');
});

// Protected User Routes (with 2FA check)
Route::middleware(['panel.detector', 'auth.user', 'two.factor'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [User\DashboardController::class, 'index'])->name('user.dashboard');

    // Domain Management
    Route::get('/domains', [User\DomainController::class, 'index'])->name('user.domains.index');
    Route::post('/domains/addon', [User\DomainController::class, 'storeAddon'])->name('user.domains.addon.store');
    Route::post('/domains/subdomain', [User\DomainController::class, 'storeSubdomain'])->name('user.domains.subdomain.store');
    Route::post('/domains/parked', [User\DomainController::class, 'storeParked'])->name('user.domains.parked.store');
    Route::delete('/domains/addon/{id}', [User\DomainController::class, 'destroyAddon'])->name('user.domains.addon.destroy');
    Route::delete('/domains/subdomain/{id}', [User\DomainController::class, 'destroySubdomain'])->name('user.domains.subdomain.destroy');
    Route::delete('/domains/parked/{id}', [User\DomainController::class, 'destroyParked'])->name('user.domains.parked.destroy');

    // Email Management
    Route::get('/email', [User\EmailController::class, 'index'])->name('user.email.index');
    Route::post('/email/accounts', [User\EmailController::class, 'storeAccount'])->name('user.email.account.store');
    Route::put('/email/accounts/{id}', [User\EmailController::class, 'updateAccount'])->name('user.email.account.update');
    Route::delete('/email/accounts/{id}', [User\EmailController::class, 'destroyAccount'])->name('user.email.account.destroy');
    Route::post('/email/forwarders', [User\EmailController::class, 'storeForwarder'])->name('user.email.forwarder.store');
    Route::delete('/email/forwarders/{id}', [User\EmailController::class, 'destroyForwarder'])->name('user.email.forwarder.destroy');
    Route::post('/email/autoresponders', [User\EmailController::class, 'storeAutoresponder'])->name('user.email.autoresponder.store');
    Route::delete('/email/autoresponders/{id}', [User\EmailController::class, 'destroyAutoresponder'])->name('user.email.autoresponder.destroy');

    // Email Deliverability (DKIM/SPF/DMARC) - CRITICAL for Gmail/Yahoo 2024
    Route::get('/email-deliverability', [User\EmailDeliverabilityController::class, 'index'])->name('user.email-deliverability.index');
    Route::post('/email-deliverability/dkim/install', [User\EmailDeliverabilityController::class, 'installDKIM'])->name('user.email-deliverability.dkim.install');
    Route::post('/email-deliverability/spf/install', [User\EmailDeliverabilityController::class, 'installSPF'])->name('user.email-deliverability.spf.install');
    Route::post('/email-deliverability/dmarc/install', [User\EmailDeliverabilityController::class, 'installDMARC'])->name('user.email-deliverability.dmarc.install');
    Route::get('/email-deliverability/{domain}/status', [User\EmailDeliverabilityController::class, 'checkStatus'])->name('user.email-deliverability.status');

    // Email Filters (Sieve)
    Route::get('/email-filters', [User\EmailFilterController::class, 'index'])->name('user.email-filters.index');
    Route::get('/email-filters/create', [User\EmailFilterController::class, 'create'])->name('user.email-filters.create');
    Route::post('/email-filters', [User\EmailFilterController::class, 'store'])->name('user.email-filters.store');
    Route::get('/email-filters/{id}/edit', [User\EmailFilterController::class, 'edit'])->name('user.email-filters.edit');
    Route::put('/email-filters/{id}', [User\EmailFilterController::class, 'update'])->name('user.email-filters.update');
    Route::delete('/email-filters/{id}', [User\EmailFilterController::class, 'destroy'])->name('user.email-filters.destroy');
    Route::post('/email-filters/{id}/toggle', [User\EmailFilterController::class, 'toggle'])->name('user.email-filters.toggle');
    Route::get('/email-filters/{id}/duplicate', [User\EmailFilterController::class, 'duplicate'])->name('user.email-filters.duplicate');
    Route::post('/email-filters/reorder', [User\EmailFilterController::class, 'reorder'])->name('user.email-filters.reorder');
    Route::post('/email-filters/test', [User\EmailFilterController::class, 'test'])->name('user.email-filters.test');
    Route::get('/email-filters/export', [User\EmailFilterController::class, 'export'])->name('user.email-filters.export');
    Route::post('/email-filters/import', [User\EmailFilterController::class, 'import'])->name('user.email-filters.import');
    Route::get('/email-filters/vacation', [User\EmailFilterController::class, 'vacation'])->name('user.email-filters.vacation');
    Route::post('/email-filters/vacation/setup', [User\EmailFilterController::class, 'setupVacation'])->name('user.email-filters.vacation.setup');
    Route::post('/email-filters/vacation/disable', [User\EmailFilterController::class, 'disableVacation'])->name('user.email-filters.vacation.disable');

    // Database Management
    Route::get('/databases', [User\DatabaseController::class, 'index'])->name('user.databases.index');
    Route::post('/databases', [User\DatabaseController::class, 'storeDatabase'])->name('user.databases.store');
    Route::delete('/databases/{id}', [User\DatabaseController::class, 'destroyDatabase'])->name('user.databases.destroy');
    Route::post('/databases/users', [User\DatabaseController::class, 'storeUser'])->name('user.databases.user.store');
    Route::delete('/databases/users/{id}', [User\DatabaseController::class, 'destroyUser'])->name('user.databases.user.destroy');
    Route::post('/databases/privileges', [User\DatabaseController::class, 'grantPrivileges'])->name('user.databases.privileges.grant');

    // File Manager
    Route::get('/files', [User\FileManagerController::class, 'index'])->name('user.files.index');
    Route::post('/files/upload', [User\FileManagerController::class, 'upload'])->name('user.files.upload');
    Route::get('/files/download', [User\FileManagerController::class, 'download'])->name('user.files.download');
    Route::post('/files/folder', [User\FileManagerController::class, 'createFolder'])->name('user.files.folder');
    Route::delete('/files', [User\FileManagerController::class, 'delete'])->name('user.files.delete');
    Route::put('/files/rename', [User\FileManagerController::class, 'rename'])->name('user.files.rename');
    Route::put('/files/chmod', [User\FileManagerController::class, 'chmod'])->name('user.files.chmod');

    // FTP Accounts
    Route::resource('ftp', User\FTPController::class);
    Route::put('/ftp/{id}/password', [User\FTPController::class, 'updatePassword'])->name('user.ftp.password');
    Route::put('/ftp/{id}/quota', [User\FTPController::class, 'updateQuota'])->name('user.ftp.quota');

    // DNS Management
    Route::get('/dns', [User\DNSController::class, 'index'])->name('user.dns.index');
    Route::get('/dns/zones/{id}', [User\DNSController::class, 'show'])->name('user.dns.show');
    Route::post('/dns/zones', [User\DNSController::class, 'storeZone'])->name('user.dns.zone.store');
    Route::post('/dns/zones/{id}/records', [User\DNSController::class, 'storeRecord'])->name('user.dns.record.store');
    Route::delete('/dns/zones/{zoneId}/records/{recordId}', [User\DNSController::class, 'destroyRecord'])->name('user.dns.record.destroy');

    // SSL Certificates
    Route::get('/ssl', [User\SSLController::class, 'index'])->name('user.ssl.index');
    Route::post('/ssl/letsencrypt', [User\SSLController::class, 'issueLetsEncrypt'])->name('user.ssl.letsencrypt');
    Route::post('/ssl/upload', [User\SSLController::class, 'uploadCertificate'])->name('user.ssl.upload');
    Route::post('/ssl/csr', [User\SSLController::class, 'generateCSR'])->name('user.ssl.csr');
    Route::delete('/ssl/{id}', [User\SSLController::class, 'destroy'])->name('user.ssl.destroy');

    // Cron Jobs
    Route::resource('cron', User\CronController::class);
    Route::post('/cron/{id}/toggle', [User\CronController::class, 'toggle'])->name('user.cron.toggle');
    Route::get('/cron/{id}/logs', [User\CronController::class, 'logs'])->name('user.cron.logs');

    // Backups
    Route::get('/backups', [User\BackupController::class, 'index'])->name('user.backups.index');
    Route::post('/backups', [User\BackupController::class, 'create'])->name('user.backups.create');
    Route::get('/backups/{id}/download', [User\BackupController::class, 'download'])->name('user.backups.download');
    Route::post('/backups/{id}/restore', [User\BackupController::class, 'restore'])->name('user.backups.restore');
    Route::delete('/backups/{id}', [User\BackupController::class, 'destroy'])->name('user.backups.destroy');

    // Website Statistics
    Route::get('/statistics', [User\StatisticsController::class, 'index'])->name('user.statistics.index');

    // Application Installer
    Route::get('/apps', [User\ApplicationInstallerController::class, 'index'])->name('user.apps.index');
    Route::get('/apps/install/{app}', [User\ApplicationInstallerController::class, 'showInstall'])->name('user.apps.install.show');
    Route::post('/apps/install/{app}', [User\ApplicationInstallerController::class, 'install'])->name('user.apps.install');
    Route::delete('/apps/{id}', [User\ApplicationInstallerController::class, 'uninstall'])->name('user.apps.uninstall');

    // Account Settings
    Route::get('/settings', [User\SettingsController::class, 'index'])->name('user.settings.index');
    Route::put('/settings/password', [User\SettingsController::class, 'updatePassword'])->name('user.settings.password');
    Route::put('/settings/email', [User\SettingsController::class, 'updateEmail'])->name('user.settings.email');

    // Two-Factor Authentication Settings
    Route::get('/settings/two-factor', [User\TwoFactorController::class, 'index'])->name('user.two-factor.index');
    Route::get('/settings/two-factor/setup', [User\TwoFactorController::class, 'setup'])->name('user.two-factor.setup');
    Route::post('/settings/two-factor/enable', [User\TwoFactorController::class, 'enable'])->name('user.two-factor.enable');
    Route::delete('/settings/two-factor/disable', [User\TwoFactorController::class, 'disable'])->name('user.two-factor.disable');
    Route::get('/settings/two-factor/backup-codes', [User\TwoFactorController::class, 'showBackupCodes'])->name('user.two-factor.backup-codes');
    Route::post('/settings/two-factor/regenerate-codes', [User\TwoFactorController::class, 'regenerateBackupCodes'])->name('user.two-factor.regenerate-codes');

    // MultiPHP Manager (Per-domain PHP version selection)
    Route::get('/multiphp', [User\MultiPHPController::class, 'index'])->name('user.multiphp.index');
    Route::post('/multiphp/set-version', [User\MultiPHPController::class, 'setVersion'])->name('user.multiphp.set-version');
    Route::get('/multiphp/ini-editor/{domain}', [User\MultiPHPController::class, 'showIniEditor'])->name('user.multiphp.ini-editor');
    Route::post('/multiphp/ini-directive', [User\MultiPHPController::class, 'updateIniDirective'])->name('user.multiphp.ini-directive');
    Route::delete('/multiphp/ini-directive', [User\MultiPHPController::class, 'deleteIniDirective'])->name('user.multiphp.ini-directive.delete');

    // Web Application Firewall (View logs and manage whitelist)
    Route::get('/waf', [User\WAFController::class, 'index'])->name('user.waf.index');
    Route::get('/waf/logs', [User\WAFController::class, 'logs'])->name('user.waf.logs');
    Route::get('/waf/logs/{id}', [User\WAFController::class, 'logDetails'])->name('user.waf.log-details');
    Route::get('/waf/whitelist', [User\WAFController::class, 'whitelist'])->name('user.waf.whitelist');
    Route::post('/waf/whitelist', [User\WAFController::class, 'addWhitelist'])->name('user.waf.whitelist.add');
    Route::delete('/waf/whitelist/{id}', [User\WAFController::class, 'removeWhitelist'])->name('user.waf.whitelist.remove');

    // Webmail Access (Roundcube with SSO)
    Route::get('/webmail', [User\WebmailController::class, 'index'])->name('user.webmail.index');
    Route::post('/webmail/login', [User\WebmailController::class, 'login'])->name('user.webmail.login');
    Route::get('/webmail/quick-login/{email}', [User\WebmailController::class, 'quickLogin'])->name('user.webmail.quick-login');
    Route::post('/webmail/validate-token', [User\WebmailController::class, 'validateToken'])->name('user.webmail.validate-token');
    Route::get('/webmail/accounts', [User\WebmailController::class, 'getEmailAccounts'])->name('user.webmail.accounts');
    Route::post('/webmail/create-default', [User\WebmailController::class, 'createDefaultEmail'])->name('user.webmail.create-default');
    Route::post('/webmail/revoke-sessions', [User\WebmailController::class, 'revokeSessions'])->name('user.webmail.revoke-sessions');

    // SpamAssassin (Email spam filtering)
    Route::get('/spamassassin', [User\SpamAssassinController::class, 'index'])->name('user.spamassassin.index');
    Route::post('/spamassassin/config', [User\SpamAssassinController::class, 'updateConfig'])->name('user.spamassassin.config');
    Route::get('/spamassassin/lists', [User\SpamAssassinController::class, 'lists'])->name('user.spamassassin.lists');
    Route::post('/spamassassin/lists', [User\SpamAssassinController::class, 'addToList'])->name('user.spamassassin.lists.add');
    Route::delete('/spamassassin/lists/{id}', [User\SpamAssassinController::class, 'removeFromList'])->name('user.spamassassin.lists.remove');
    Route::get('/spamassassin/training', [User\SpamAssassinController::class, 'training'])->name('user.spamassassin.training');
    Route::post('/spamassassin/train', [User\SpamAssassinController::class, 'train'])->name('user.spamassassin.train');
    Route::get('/spamassassin/logs', [User\SpamAssassinController::class, 'logs'])->name('user.spamassassin.logs');

    // OVH Cloud Resources
    Route::get('/ovh', [User\OVHController::class, 'index'])->name('user.ovh.index');
    Route::get('/ovh/servers/{id}', [User\OVHController::class, 'serverDetails'])->name('user.ovh.server-details');
    Route::post('/ovh/servers/{id}/reboot', [User\OVHController::class, 'rebootServer'])->name('user.ovh.server-reboot');
    Route::get('/ovh/instances/{id}', [User\OVHController::class, 'instanceDetails'])->name('user.ovh.instance-details');
    Route::post('/ovh/instances/{id}/control', [User\OVHController::class, 'controlInstance'])->name('user.ovh.instance-control');
    Route::get('/ovh/billing', [User\OVHController::class, 'billing'])->name('user.ovh.billing');
    Route::get('/ovh/servers/{id}/stats', [User\OVHController::class, 'serverStats'])->name('user.ovh.server-stats');
    Route::get('/ovh/monitoring', [User\OVHController::class, 'monitoring'])->name('user.ovh.monitoring');
});

// Redirect root to dashboard
Route::get('/', fn() => redirect()->route('user.dashboard'))->middleware(['panel.detector', 'auth.user']);
