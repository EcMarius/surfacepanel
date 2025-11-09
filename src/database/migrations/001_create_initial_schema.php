<?php

use VirPanel\Database\Migration;
use VirPanel\Database\Schema\Blueprint;
use VirPanel\Database\Schema\Schema;

/**
 * Initial Database Schema Migration
 *
 * Creates all core tables for VirPanel
 */
return new class extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        // Users table (WHM admin users)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->unique();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('full_name', 255)->nullable();
            $table->enum('role', ['root', 'admin', 'reseller'])->default('admin');
            $table->boolean('is_active')->default(true);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret', 255)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['username', 'is_active']);
            $table->index(['email', 'is_active']);
        });

        // Accounts table (cPanel user accounts)
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('username', 64)->unique();
            $table->string('domain', 255)->unique();
            $table->string('email', 255);
            $table->string('password', 255);
            $table->string('home_directory', 500);
            $table->integer('disk_used')->default(0); // MB
            $table->integer('disk_quota')->default(0); // MB, 0 = unlimited
            $table->integer('bandwidth_used')->default(0); // MB
            $table->integer('bandwidth_quota')->default(0); // MB, 0 = unlimited
            $table->integer('inodes_used')->default(0);
            $table->integer('inodes_quota')->default(0); // 0 = unlimited
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->boolean('shell_access')->default(false);
            $table->string('shell', 100)->default('/bin/bash');
            $table->string('contact_email', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('dedicated_ip')->default(false);
            $table->string('theme', 100)->default('default');
            $table->string('locale', 10)->default('en_US');
            $table->string('timezone', 50)->default('UTC');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['username', 'status']);
            $table->index(['domain', 'status']);
            $table->index(['reseller_id', 'status']);
            $table->index('created_at');
        });

        // Packages table (hosting plans)
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100)->unique();
            $table->string('display_name', 255);
            $table->text('description')->nullable();
            $table->integer('disk_quota')->default(0); // MB, 0 = unlimited
            $table->integer('bandwidth_quota')->default(0); // MB, 0 = unlimited
            $table->integer('inodes_quota')->default(0); // 0 = unlimited
            $table->integer('max_addon_domains')->default(0); // 0 = unlimited
            $table->integer('max_parked_domains')->default(0);
            $table->integer('max_subdomains')->default(0);
            $table->integer('max_email_accounts')->default(0);
            $table->integer('max_email_lists')->default(0);
            $table->integer('max_databases')->default(0);
            $table->integer('max_ftp_accounts')->default(0);
            $table->boolean('shell_access')->default(false);
            $table->boolean('cgi_access')->default(true);
            $table->boolean('dedicated_ip')->default(false);
            $table->boolean('private_nameservers')->default(false);
            $table->boolean('ssl_support')->default(true);
            $table->boolean('wildcard_ssl')->default(false);
            $table->integer('max_hourly_emails')->default(0); // 0 = unlimited
            $table->integer('max_daily_emails')->default(0);
            $table->boolean('backup_enabled')->default(true);
            $table->integer('backup_retention_days')->default(7);
            $table->boolean('autossl_enabled')->default(true);
            $table->json('php_versions')->nullable(); // ['8.1', '8.2']
            $table->json('features')->nullable(); // Additional features
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'is_active']);
        });

        // Domains table
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 255);
            $table->enum('type', ['main', 'addon', 'parked', 'subdomain']);
            $table->string('document_root', 500);
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_certificate_id', 255)->nullable();
            $table->boolean('autossl_enabled')->default(true);
            $table->timestamp('ssl_expires_at')->nullable();
            $table->boolean('redirect_www')->default(false);
            $table->boolean('force_https')->default(false);
            $table->string('redirect_url', 500)->nullable();
            $table->integer('redirect_code')->nullable(); // 301, 302
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['domain', 'account_id']);
            $table->index(['account_id', 'type']);
        });

        // DNS Zones table
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('zone', 255)->unique();
            $table->string('primary_ns', 255);
            $table->string('hostmaster', 255);
            $table->bigInteger('serial')->default(1);
            $table->integer('refresh')->default(86400);
            $table->integer('retry')->default(7200);
            $table->integer('expire')->default(3600000);
            $table->integer('ttl')->default(86400);
            $table->boolean('dnssec_enabled')->default(false);
            $table->timestamps();

            $table->index('account_id');
        });

        // DNS Records table
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('dns_zones')->cascadeOnDelete();
            $table->string('name', 255);
            $table->enum('type', ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR']);
            $table->string('content', 1000);
            $table->integer('ttl')->default(3600);
            $table->integer('priority')->nullable(); // For MX, SRV
            $table->integer('weight')->nullable(); // For SRV
            $table->integer('port')->nullable(); // For SRV
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'type']);
            $table->index(['name', 'type']);
        });

        // Email Accounts table
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->integer('quota')->default(250); // MB, 0 = unlimited
            $table->integer('used')->default(0); // MB
            $table->boolean('spam_filter_enabled')->default(true);
            $table->integer('spam_score_threshold')->default(5);
            $table->boolean('autoresponder_enabled')->default(false);
            $table->text('autoresponder_message')->nullable();
            $table->timestamp('autoresponder_start')->nullable();
            $table->timestamp('autoresponder_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
            $table->index(['domain_id', 'is_active']);
        });

        // Email Forwarders table
        Schema::create('email_forwarders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('source', 255);
            $table->text('destination'); // Can be multiple, JSON array
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['domain_id', 'is_active']);
        });

        // Databases table
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255)->unique();
            $table->enum('type', ['mysql', 'postgresql'])->default('mysql');
            $table->string('charset', 50)->default('utf8mb4');
            $table->string('collation', 50)->default('utf8mb4_unicode_ci');
            $table->bigInteger('size')->default(0); // bytes
            $table->timestamps();

            $table->index(['account_id', 'type']);
        });

        // Database Users table
        Schema::create('database_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('username', 255)->unique();
            $table->string('password', 255);
            $table->string('host', 255)->default('localhost');
            $table->timestamps();

            $table->index('account_id');
        });

        // Database Permissions table
        Schema::create('database_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_id')->constrained()->cascadeOnDelete();
            $table->foreignId('database_user_id')->constrained()->cascadeOnDelete();
            $table->json('privileges'); // ['SELECT', 'INSERT', 'UPDATE', 'DELETE', ...]
            $table->timestamps();

            $table->unique(['database_id', 'database_user_id']);
        });

        // FTP Accounts table
        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('username', 255)->unique();
            $table->string('password', 255);
            $table->string('home_directory', 500);
            $table->integer('quota')->default(0); // MB, 0 = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
        });

        // SSL Certificates table
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 255);
            $table->enum('type', ['self_signed', 'lets_encrypt', 'commercial', 'custom']);
            $table->text('certificate');
            $table->text('private_key');
            $table->text('chain')->nullable();
            $table->string('issuer', 255)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('auto_renew')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'domain']);
            $table->index(['expires_at', 'auto_renew']);
        });

        // Backups table
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['full', 'home', 'mysql', 'email', 'ssl']);
            $table->string('filename', 500);
            $table->string('storage_path', 500);
            $table->bigInteger('size')->default(0); // bytes
            $table->string('checksum', 64)->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'type', 'status']);
            $table->index('expires_at');
        });

        // Modules table (installed modules tracking)
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 255);
            $table->text('description')->nullable();
            $table->string('version', 20);
            $table->string('author', 255)->nullable();
            $table->string('license', 50)->nullable();
            $table->string('namespace', 255);
            $table->string('path', 500);
            $table->json('dependencies')->nullable();
            $table->json('permissions')->nullable();
            $table->json('hooks')->nullable();
            $table->json('routes')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamp('installed_at');
            $table->timestamps();

            $table->index(['is_enabled', 'is_system']);
        });

        // Templates table
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 255);
            $table->text('description')->nullable();
            $table->string('version', 20);
            $table->string('author', 255)->nullable();
            $table->enum('type', ['admin', 'user', 'both'])->default('user');
            $table->string('path', 500);
            $table->string('preview_image', 500)->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // Licenses table
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 255)->unique();
            $table->string('server_ip', 45);
            $table->string('server_hostname', 255);
            $table->enum('type', ['monthly', 'yearly', 'lifetime', 'trial']);
            $table->enum('status', ['active', 'suspended', 'expired', 'cancelled'])->default('active');
            $table->integer('max_accounts')->default(0); // 0 = unlimited
            $table->json('features')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['license_key', 'status']);
            $table->index('expires_at');
        });

        // API Tokens table
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('token', 64)->unique();
            $table->json('scopes')->nullable(); // ['accounts:read', 'domains:write']
            $table->string('ip_whitelist', 1000)->nullable(); // Comma-separated IPs
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'expires_at']);
        });

        // Cloudflare Integrations table
        Schema::create('cloudflare_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('api_key', 255);
            $table->string('zone_id', 100)->nullable();
            $table->string('zone_name', 255)->nullable();
            $table->boolean('proxy_enabled')->default(true);
            $table->boolean('auto_sync')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
        });

        // Resellers table (extends users)
        Schema::create('reseller_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name', 255)->nullable();
            $table->string('company_logo', 500)->nullable();
            $table->text('company_address')->nullable();
            $table->string('support_email', 255)->nullable();
            $table->string('support_phone', 50)->nullable();
            $table->string('support_url', 500)->nullable();
            $table->integer('max_accounts')->default(0); // 0 = unlimited
            $table->integer('disk_quota')->default(0); // MB, 0 = unlimited
            $table->integer('bandwidth_quota')->default(0); // MB, 0 = unlimited
            $table->boolean('can_create_packages')->default(true);
            $table->boolean('can_oversell')->default(false);
            $table->boolean('white_label')->default(false);
            $table->string('nameserver1', 255)->nullable();
            $table->string('nameserver2', 255)->nullable();
            $table->string('nameserver3', 255)->nullable();
            $table->string('nameserver4', 255)->nullable();
            $table->json('allowed_features')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // Cron Jobs table
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('command', 1000);
            $table->string('minute', 100)->default('*');
            $table->string('hour', 100)->default('*');
            $table->string('day', 100)->default('*');
            $table->string('month', 100)->default('*');
            $table->string('weekday', 100)->default('*');
            $table->string('email_output', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
        });

        // Audit Logs table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 255);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['account_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        // Sessions table
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');

            $table->index('user_id');
            $table->index('last_activity');
        });

        // Settings table (global system settings)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string'); // string, int, bool, json
            $table->string('group', 100)->default('general');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->index(['group', 'key']);
        });

        // Notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->string('title', 255);
            $table->text('message');
            $table->json('data')->nullable();
            $table->enum('level', ['info', 'success', 'warning', 'error'])->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['account_id', 'read_at']);
        });

        // Queue Jobs table
        Schema::create('queue_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100)->index();
            $table->text('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');

            $table->index(['queue', 'reserved_at']);
        });

        // Failed Jobs table
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 255)->unique();
            $table->text('connection');
            $table->text('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('queue_jobs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cron_jobs');
        Schema::dropIfExists('reseller_settings');
        Schema::dropIfExists('cloudflare_integrations');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('ssl_certificates');
        Schema::dropIfExists('ftp_accounts');
        Schema::dropIfExists('database_permissions');
        Schema::dropIfExists('database_users');
        Schema::dropIfExists('databases');
        Schema::dropIfExists('email_forwarders');
        Schema::dropIfExists('email_accounts');
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('users');
    }
};
