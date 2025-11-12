<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table (for both admin and user authentication)
        Schema::create('vp_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('username')->unique();
            $table->enum('role', ['root', 'admin', 'reseller', 'user'])->default('user');
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('status');
        });

        // Accounts table
        Schema::create('vp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->foreignId('package_id')->constrained('vp_packages');
            $table->foreignId('reseller_id')->nullable()->constrained('vp_users');
            $table->string('username')->unique();
            $table->string('domain')->unique();
            $table->string('primary_ip');
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->integer('disk_used')->default(0); // MB
            $table->integer('bandwidth_used')->default(0); // MB
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();

            $table->index('username');
            $table->index('status');
        });

        // Packages table
        Schema::create('vp_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->integer('disk_quota')->default(-1); // -1 = unlimited
            $table->integer('bandwidth_quota')->default(-1);
            $table->integer('email_accounts')->default(-1);
            $table->integer('databases')->default(-1);
            $table->integer('ftp_accounts')->default(-1);
            $table->integer('addon_domains')->default(-1);
            $table->integer('subdomains')->default(-1);
            $table->integer('parked_domains')->default(-1);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Addon Domains
        Schema::create('vp_addon_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('domain')->unique();
            $table->string('document_root');
            $table->timestamps();

            $table->index('domain');
        });

        // Subdomains
        Schema::create('vp_subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('subdomain');
            $table->string('parent_domain');
            $table->string('document_root');
            $table->timestamps();

            $table->unique(['subdomain', 'parent_domain']);
        });

        // Parked Domains
        Schema::create('vp_parked_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('domain')->unique();
            $table->string('target_domain');
            $table->timestamps();
        });

        // Email Accounts
        Schema::create('vp_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('email')->unique();
            $table->string('password');
            $table->integer('quota')->default(250); // MB
            $table->integer('used')->default(0);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('email');
        });

        // Email Forwarders
        Schema::create('vp_email_forwarders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('source');
            $table->text('destination'); // Can be multiple addresses
            $table->timestamps();
        });

        // Email Autoresponders
        Schema::create('vp_email_autoresponders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('email');
            $table->string('subject');
            $table->text('body');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Databases
        Schema::create('vp_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('database_name')->unique();
            $table->integer('size')->default(0); // MB
            $table->timestamps();
        });

        // Database Users
        Schema::create('vp_database_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('username')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // FTP Accounts
        Schema::create('vp_ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('home_directory');
            $table->integer('quota')->default(-1); // MB, -1 = unlimited
            $table->integer('uid');
            $table->integer('gid');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
        });

        // DNS Zones
        Schema::create('vp_dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('domain')->unique();
            $table->string('soa');
            $table->bigInteger('serial');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // DNS Records
        Schema::create('vp_dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('vp_dns_zones')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA']);
            $table->string('value');
            $table->integer('ttl')->default(3600);
            $table->integer('priority')->nullable(); // For MX, SRV
            $table->timestamps();

            $table->index(['zone_id', 'type']);
        });

        // SSL Certificates
        Schema::create('vp_ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('domain');
            $table->text('certificate');
            $table->text('private_key');
            $table->text('ca_bundle')->nullable();
            $table->enum('type', ['letsencrypt', 'custom'])->default('custom');
            $table->timestamp('expires_at');
            $table->enum('status', ['active', 'expired', 'revoked'])->default('active');
            $table->timestamps();

            $table->index('domain');
            $table->index('expires_at');
        });

        // Cron Jobs
        Schema::create('vp_cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('schedule'); // Cron syntax
            $table->text('command');
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_run')->nullable();
            $table->timestamp('next_run')->nullable();
            $table->timestamps();
        });

        // Backups
        Schema::create('vp_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('filename');
            $table->enum('type', ['full', 'files', 'databases', 'email']);
            $table->bigInteger('size')->default(0); // bytes
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Installed Applications
        Schema::create('vp_installed_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('app_name');
            $table->string('app_version');
            $table->string('domain');
            $table->string('directory')->nullable();
            $table->string('install_path');
            $table->string('database_name')->nullable();
            $table->string('admin_url')->nullable();
            $table->timestamps();
        });

        // Access Logs (for statistics)
        Schema::create('vp_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('host');
            $table->string('remote_addr');
            $table->string('request_uri');
            $table->integer('status');
            $table->bigInteger('bytes_sent')->default(0);
            $table->string('http_referer')->nullable();
            $table->string('http_user_agent')->nullable();
            $table->timestamp('timestamp');

            $table->index(['username', 'host', 'timestamp']);
            $table->index('timestamp');
        });

        // IP Addresses
        Schema::create('vp_ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->string('subnet_mask')->default('255.255.255.0');
            $table->string('gateway')->nullable();
            $table->string('ptr_record')->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Resellers
        Schema::create('vp_resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->integer('account_limit')->default(-1);
            $table->integer('disk_limit')->default(-1); // MB
            $table->integer('bandwidth_limit')->default(-1); // MB
            $table->decimal('discount_rate', 5, 2)->default(0); // Percentage
            $table->boolean('can_create_packages')->default(false);
            $table->timestamps();
        });

        // Audit Logs
        Schema::create('vp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('vp_users')->onDelete('set null');
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->string('action');
            $table->text('description');
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('action');
        });

        // API Tokens
        Schema::create('vp_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->string('name');
            $table->string('token', 80)->unique();
            $table->json('permissions')->nullable();
            $table->integer('rate_limit')->default(100); // requests per minute
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('token');
        });

        // PHP Versions (System-wide available PHP versions)
        Schema::create('vp_php_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique(); // e.g., '8.2', '8.1', '7.4'
            $table->string('binary_path'); // e.g., '/usr/bin/php8.2'
            $table->string('fpm_pool_dir'); // e.g., '/etc/php/8.2/fpm/pool.d'
            $table->string('php_ini_path'); // e.g., '/etc/php/8.2/fpm/php.ini'
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('extensions')->nullable(); // Available extensions
            $table->timestamps();

            $table->index('version');
            $table->index('is_default');
        });

        // Domain PHP Settings (Per-domain PHP configuration)
        Schema::create('vp_domain_php_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('domain'); // Can be main, addon, or subdomain
            $table->foreignId('php_version_id')->constrained('vp_php_versions');
            $table->string('fpm_pool_name')->nullable(); // Generated pool name
            $table->integer('fpm_max_children')->default(5);
            $table->integer('fpm_start_servers')->default(2);
            $table->integer('fpm_min_spare_servers')->default(1);
            $table->integer('fpm_max_spare_servers')->default(3);
            $table->timestamps();

            $table->unique(['account_id', 'domain']);
            $table->index('domain');
        });

        // PHP.ini Overrides (Per-domain PHP.ini customization)
        Schema::create('vp_php_ini_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_php_setting_id')->constrained('vp_domain_php_settings')->onDelete('cascade');
            $table->string('directive'); // e.g., 'memory_limit', 'upload_max_filesize'
            $table->string('value'); // e.g., '256M', '128M'
            $table->timestamps();

            $table->unique(['domain_php_setting_id', 'directive']);
            $table->index('directive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vp_php_ini_overrides');
        Schema::dropIfExists('vp_domain_php_settings');
        Schema::dropIfExists('vp_php_versions');
        Schema::dropIfExists('vp_api_tokens');
        Schema::dropIfExists('vp_audit_logs');
        Schema::dropIfExists('vp_resellers');
        Schema::dropIfExists('vp_ip_addresses');
        Schema::dropIfExists('vp_access_logs');
        Schema::dropIfExists('vp_installed_apps');
        Schema::dropIfExists('vp_backups');
        Schema::dropIfExists('vp_cron_jobs');
        Schema::dropIfExists('vp_ssl_certificates');
        Schema::dropIfExists('vp_dns_records');
        Schema::dropIfExists('vp_dns_zones');
        Schema::dropIfExists('vp_ftp_accounts');
        Schema::dropIfExists('vp_database_users');
        Schema::dropIfExists('vp_databases');
        Schema::dropIfExists('vp_email_autoresponders');
        Schema::dropIfExists('vp_email_forwarders');
        Schema::dropIfExists('vp_email_accounts');
        Schema::dropIfExists('vp_parked_domains');
        Schema::dropIfExists('vp_subdomains');
        Schema::dropIfExists('vp_addon_domains');
        Schema::dropIfExists('vp_accounts');
        Schema::dropIfExists('vp_packages');
        Schema::dropIfExists('vp_users');
    }
};
