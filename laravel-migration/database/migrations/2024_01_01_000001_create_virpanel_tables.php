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

        // Email Filters (Sieve)
        Schema::create('vp_email_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('email'); // Email address this filter applies to
            $table->string('name'); // Filter name
            $table->text('description')->nullable();
            $table->integer('priority')->default(100); // Lower = higher priority
            $table->boolean('is_active')->default(true);
            $table->enum('match_type', ['all', 'any'])->default('all'); // Match all or any conditions
            $table->boolean('stop_processing')->default(false); // Stop processing further filters
            $table->timestamps();

            $table->index('email');
            $table->index('priority');
            $table->index('is_active');
        });

        // Email Filter Conditions (IF conditions)
        Schema::create('vp_email_filter_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_id')->constrained('vp_email_filters')->onDelete('cascade');
            $table->enum('field', [
                'from',
                'to',
                'subject',
                'body',
                'header',
                'size',
                'spam_score',
                'recipient',
                'sender'
            ])->default('from');
            $table->string('header_name')->nullable(); // For custom header matching
            $table->enum('operator', [
                'contains',
                'not_contains',
                'equals',
                'not_equals',
                'begins_with',
                'ends_with',
                'matches_regex',
                'greater_than',
                'less_than'
            ])->default('contains');
            $table->text('value'); // Value to match against
            $table->boolean('case_sensitive')->default(false);
            $table->timestamps();

            $table->index('filter_id');
        });

        // Email Filter Actions (THEN actions)
        Schema::create('vp_email_filter_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_id')->constrained('vp_email_filters')->onDelete('cascade');
            $table->enum('action_type', [
                'move_to_folder',
                'forward',
                'redirect',
                'delete',
                'reject',
                'discard',
                'mark_as_read',
                'flag',
                'pipe_to_program',
                'vacation',
                'stop'
            ])->default('move_to_folder');
            $table->text('action_value')->nullable(); // Folder name, email address, program path, etc.
            $table->text('vacation_message')->nullable(); // For vacation auto-responder
            $table->string('vacation_subject')->nullable();
            $table->integer('vacation_days')->nullable(); // Days between vacation responses
            $table->integer('order')->default(1); // Action execution order
            $table->timestamps();

            $table->index('filter_id');
            $table->index('order');
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

        // WAF/ModSecurity Configuration
        Schema::create('vp_waf_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->enum('mode', ['off', 'detection', 'blocking'])->default('blocking');
            $table->boolean('use_owasp_crs')->default(true); // OWASP Core Rule Set
            $table->integer('paranoia_level')->default(1); // 1-4
            $table->json('disabled_rules')->nullable(); // Array of rule IDs to disable
            $table->json('custom_rules')->nullable(); // Custom ModSecurity rules
            $table->timestamps();
        });

        // WAF Rules (Custom and managed rules)
        Schema::create('vp_waf_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_id')->unique(); // e.g., 'custom-001', 'owasp-crs-942100'
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['custom', 'owasp_crs', 'managed'])->default('custom');
            $table->text('rule_content'); // ModSecurity rule syntax
            $table->integer('severity')->default(3); // 1=critical, 5=info
            $table->boolean('is_active')->default(true);
            $table->integer('false_positive_count')->default(0);
            $table->timestamps();

            $table->index('rule_id');
            $table->index('type');
            $table->index('is_active');
        });

        // WAF Logs (Attack logs and blocked requests)
        Schema::create('vp_waf_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->string('domain')->nullable();
            $table->string('client_ip');
            $table->string('request_method', 10); // GET, POST, etc.
            $table->string('request_uri', 500);
            $table->text('user_agent')->nullable();
            $table->string('attack_type'); // SQL injection, XSS, etc.
            $table->string('rule_id'); // Which rule triggered
            $table->integer('severity'); // 1-5
            $table->enum('action', ['blocked', 'logged', 'challenged'])->default('blocked');
            $table->text('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->text('matched_data')->nullable(); // What triggered the rule
            $table->string('country_code', 2)->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('domain');
            $table->index('client_ip');
            $table->index('attack_type');
            $table->index('created_at');
        });

        // WAF Whitelist (Trusted IPs and patterns)
        Schema::create('vp_waf_whitelist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->enum('type', ['ip', 'ip_range', 'user_agent', 'uri_pattern'])->default('ip');
            $table->string('value'); // IP address, CIDR, pattern, etc.
            $table->string('description')->nullable();
            $table->boolean('is_global')->default(false); // Global whitelist (admin only)
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('is_global');
            $table->index('expires_at');
        });

        // WAF Blacklist (Blocked IPs and patterns)
        Schema::create('vp_waf_blacklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->enum('type', ['ip', 'ip_range', 'user_agent', 'uri_pattern'])->default('ip');
            $table->string('value');
            $table->string('reason')->nullable();
            $table->boolean('is_global')->default(false); // Global blacklist (admin only)
            $table->timestamp('expires_at')->nullable();
            $table->integer('hit_count')->default(0); // Number of times blocked
            $table->timestamps();

            $table->index('type');
            $table->index('is_global');
            $table->index('expires_at');
        });

        // WAF Statistics (Aggregated statistics)
        Schema::create('vp_waf_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('cascade');
            $table->date('date');
            $table->integer('total_requests')->default(0);
            $table->integer('blocked_requests')->default(0);
            $table->integer('sql_injection_attempts')->default(0);
            $table->integer('xss_attempts')->default(0);
            $table->integer('lfi_attempts')->default(0); // Local File Inclusion
            $table->integer('rfi_attempts')->default(0); // Remote File Inclusion
            $table->integer('rce_attempts')->default(0); // Remote Code Execution
            $table->json('top_attack_ips')->nullable(); // Top 10 attacking IPs
            $table->json('top_attacked_urls')->nullable(); // Top 10 targeted URLs
            $table->timestamps();

            $table->unique(['account_id', 'date']);
            $table->index('date');
        });

        // Firewall Configuration (CSF-like functionality)
        Schema::create('vp_firewall_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->enum('default_policy', ['accept', 'drop', 'reject'])->default('drop');
            $table->boolean('block_ping')->default(false);
            $table->boolean('syn_flood_protection')->default(true);
            $table->integer('connection_limit')->default(100); // Connections per IP
            $table->integer('port_scan_threshold')->default(10);
            $table->integer('login_failure_threshold')->default(5);
            $table->integer('login_failure_ban_time')->default(3600); // seconds
            $table->json('allowed_countries')->nullable(); // Country codes
            $table->json('blocked_countries')->nullable();
            $table->timestamps();
        });

        // Firewall Rules (iptables rules)
        Schema::create('vp_firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('chain', ['input', 'output', 'forward'])->default('input');
            $table->enum('protocol', ['tcp', 'udp', 'icmp', 'all'])->default('tcp');
            $table->string('source_ip')->nullable(); // Can be IP or CIDR
            $table->string('destination_ip')->nullable();
            $table->integer('source_port')->nullable();
            $table->integer('destination_port')->nullable();
            $table->enum('action', ['accept', 'drop', 'reject'])->default('accept');
            $table->integer('priority')->default(100); // Lower = higher priority
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('chain');
            $table->index('is_active');
            $table->index('priority');
        });

        // Open Ports (Managed ports)
        Schema::create('vp_firewall_ports', function (Blueprint $table) {
            $table->id();
            $table->integer('port');
            $table->enum('protocol', ['tcp', 'udp', 'both'])->default('tcp');
            $table->string('service_name')->nullable(); // e.g., SSH, HTTP, MySQL
            $table->enum('direction', ['inbound', 'outbound', 'both'])->default('inbound');
            $table->boolean('is_open')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['port', 'protocol']);
            $table->index('is_open');
        });

        // IP Allow List (Firewall level - different from WAF)
        Schema::create('vp_firewall_allow', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address'); // Can be single IP or CIDR
            $table->text('comment')->nullable();
            $table->boolean('is_permanent')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('ip_address');
            $table->index('expires_at');
        });

        // IP Deny List (Firewall level)
        Schema::create('vp_firewall_deny', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('reason')->nullable();
            $table->enum('source', ['manual', 'lfd', 'port_scan', 'brute_force'])->default('manual');
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->integer('violations')->default(1);
            $table->timestamps();

            $table->index('ip_address');
            $table->index('source');
            $table->index('expires_at');
        });

        // Login Failure Daemon logs (LFD - auto-bans brute force)
        Schema::create('vp_firewall_login_failures', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->string('service'); // ssh, ftp, webmail, admin, user
            $table->string('username')->nullable();
            $table->integer('failure_count')->default(1);
            $table->timestamp('first_attempt_at');
            $table->timestamp('last_attempt_at');
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_until')->nullable();
            $table->timestamps();

            $table->index('ip_address');
            $table->index('service');
            $table->index('is_banned');
        });

        // Connection Tracking (Monitor active connections)
        Schema::create('vp_firewall_connections', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->integer('port');
            $table->enum('protocol', ['tcp', 'udp'])->default('tcp');
            $table->integer('connection_count')->default(0);
            $table->timestamp('last_seen_at');
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->index('ip_address');
            $table->index('last_seen_at');
        });

        // Port Scan Detection
        Schema::create('vp_firewall_port_scans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address');
            $table->json('scanned_ports'); // Array of ports that were scanned
            $table->integer('scan_count')->default(1);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();

            $table->index('ip_address');
            $table->index('is_blocked');
        });

        // Webmail Configuration (Roundcube installation settings)
        Schema::create('vp_webmail_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_installed')->default(false);
            $table->string('version')->nullable(); // Roundcube version
            $table->string('installation_path')->default('/var/www/roundcube');
            $table->string('database_name')->default('roundcube');
            $table->string('database_user')->nullable();
            $table->string('database_password')->nullable();
            $table->string('des_key')->nullable(); // Encryption key for Roundcube
            $table->json('enabled_plugins')->nullable(); // Array of enabled plugins
            $table->string('default_theme')->default('elastic');
            $table->string('imap_host')->default('localhost:143');
            $table->string('smtp_host')->default('localhost:587');
            $table->boolean('smtp_auth')->default(true);
            $table->string('product_name')->default('VirPanel Webmail');
            $table->boolean('force_https')->default(true);
            $table->integer('session_lifetime')->default(30); // minutes
            $table->json('settings')->nullable(); // Additional Roundcube settings
            $table->timestamps();
        });

        // Webmail SSO Sessions (for auto-login tokens)
        Schema::create('vp_webmail_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('vp_users')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('email'); // Email account to login as
            $table->string('token', 64)->unique(); // SSO token
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('token');
            $table->index('email');
            $table->index('expires_at');
        });


        // OVH Configuration (API credentials)
        Schema::create('vp_ovh_config', function (Blueprint $table) {
            $table->id();
            $table->string('application_key')->nullable();
            $table->string('application_secret')->nullable();
            $table->string('consumer_key')->nullable();
            $table->enum('endpoint', ['ovh-eu', 'ovh-ca', 'ovh-us', 'kimsufi-eu', 'kimsufi-ca', 'soyoustart-eu', 'soyoustart-ca'])->default('ovh-eu');
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('webhook_url')->nullable(); // For OVH webhooks
            $table->timestamps();
        });

        // OVH Servers (Managed servers)
        Schema::create('vp_ovh_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->string('service_name')->unique(); // OVH service name (e.g., ns12345.ip-1-2-3.eu)
            $table->enum('server_type', ['dedicated', 'vps', 'public_cloud', 'private_cloud'])->default('dedicated');
            $table->string('display_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('additional_ips')->nullable(); // Array of failover IPs
            $table->string('datacenter')->nullable(); // e.g., rbx, sbg, gra
            $table->string('os')->nullable(); // Operating system
            $table->string('status')->default('active'); // active, suspended, terminated
            $table->integer('cpu_cores')->nullable();
            $table->integer('ram_mb')->nullable(); // RAM in MB
            $table->integer('disk_gb')->nullable(); // Disk in GB
            $table->integer('bandwidth_mbps')->nullable(); // Bandwidth in Mbps
            $table->json('vrack_info')->nullable(); // vRack configuration
            $table->text('notes')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->index('service_name');
            $table->index('server_type');
            $table->index('status');
        });

        // OVH Billing (Cost tracking)
        Schema::create('vp_ovh_billing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('vp_ovh_servers')->onDelete('cascade');
            $table->string('bill_id')->nullable(); // OVH bill ID
            $table->date('billing_date');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->enum('billing_type', ['monthly', 'hourly', 'one_time', 'bandwidth_overage'])->default('monthly');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable(); // Additional billing information
            $table->timestamps();

            $table->index('server_id');
            $table->index('billing_date');
            $table->index('status');
        });

        // OVH Public Cloud Instances
        Schema::create('vp_ovh_cloud_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->string('instance_id')->unique(); // OVH instance ID
            $table->string('project_id'); // OVH project ID
            $table->string('name');
            $table->string('flavor_id'); // Instance type (s1-2, b2-7, etc.)
            $table->string('image_id'); // OS image ID
            $table->string('region'); // GRA, SBG, BHS, etc.
            $table->string('ip_address')->nullable();
            $table->json('ip_addresses')->nullable(); // All IPs (public/private)
            $table->enum('status', ['active', 'stopped', 'building', 'error', 'deleted'])->default('building');
            $table->integer('vcpus')->nullable();
            $table->integer('ram_mb')->nullable();
            $table->integer('disk_gb')->nullable();
            $table->boolean('monthly_billing')->default(false);
            $table->decimal('hourly_rate', 10, 4)->nullable();
            $table->decimal('monthly_rate', 10, 2)->nullable();
            $table->timestamps();

            $table->index('instance_id');
            $table->index('project_id');
            $table->index('status');
        });

        // OVH Load Balancers
        Schema::create('vp_ovh_load_balancers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->string('service_name')->unique();
            $table->string('display_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->json('backend_servers')->nullable(); // Array of backend server IPs
            $table->enum('protocol', ['http', 'https', 'tcp', 'udp'])->default('http');
            $table->integer('port')->default(80);
            $table->string('algorithm')->default('roundrobin'); // roundrobin, leastconn, source
            $table->boolean('ssl_enabled')->default(false);
            $table->text('ssl_certificate')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('service_name');
            $table->index('status');
        });

        // OVH Object Storage (Swift)
        Schema::create('vp_ovh_object_storage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->string('container_name')->unique();
            $table->string('project_id');
            $table->string('region');
            $table->enum('type', ['public', 'private', 'static'])->default('private');
            $table->bigInteger('objects_count')->default(0);
            $table->bigInteger('bytes_used')->default(0); // Storage used in bytes
            $table->string('cdn_url')->nullable(); // CDN URL if enabled
            $table->boolean('cdn_enabled')->default(false);
            $table->timestamps();

            $table->index('container_name');
            $table->index('project_id');
        });

        // OVH Network (vRack)
        Schema::create('vp_ovh_vrack', function (Blueprint $table) {
            $table->id();
            $table->string('vrack_id')->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->json('allowed_services')->nullable(); // Services allowed in vRack
            $table->json('servers')->nullable(); // Array of server service names
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('vrack_id');
        });

        // OVH Failover IPs
        Schema::create('vp_ovh_failover_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('vp_ovh_servers')->onDelete('set null');
            $table->string('ip_address')->unique();
            $table->string('netmask')->default('255.255.255.255');
            $table->string('routed_to')->nullable(); // Service name it's routed to
            $table->string('reverse_dns')->nullable();
            $table->enum('status', ['active', 'parked'])->default('parked');
            $table->timestamps();

            $table->index('ip_address');
            $table->index('routed_to');
        });

        // cPanel/WHM Migrations (Track migration progress)
        Schema::create('vp_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('backup_filename');
            $table->string('backup_path');
            $table->bigInteger('backup_size')->default(0); // bytes
            $table->enum('source_type', ['cpanel', 'whm', 'plesk', 'directadmin'])->default('cpanel');
            $table->enum('status', ['pending', 'uploading', 'parsing', 'processing', 'completed', 'failed', 'rolled_back'])->default('pending');
            $table->integer('progress_percentage')->default(0);
            $table->string('current_step')->nullable(); // What's currently being migrated
            $table->json('migration_summary')->nullable(); // Summary of what will be migrated
            $table->json('migration_results')->nullable(); // Results after migration
            $table->json('validation_errors')->nullable(); // Any validation errors found
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('vp_users')->onDelete('set null');
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });

        // Migration detailed logs
        Schema::create('vp_migration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('migration_id')->constrained('vp_migrations')->onDelete('cascade');
            $table->enum('level', ['info', 'warning', 'error', 'success'])->default('info');
            $table->enum('category', ['account', 'domain', 'email', 'database', 'dns', 'ssl', 'cron', 'ftp', 'file'])->nullable();
            $table->string('item_name')->nullable(); // Name of the item being migrated (email address, domain, etc.)
            $table->text('message');
            $table->json('details')->nullable(); // Additional details as JSON
            $table->timestamps();

            $table->index('migration_id');
            $table->index('level');
            $table->index('category');
        });

        // SpamAssassin Configuration (Per-account settings)
        Schema::create('vp_spamassassin_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->boolean('is_enabled')->default(true);
            $table->decimal('spam_threshold', 4, 2)->default(5.0); // Spam score threshold (0-10)
            $table->enum('spam_action', ['delete', 'quarantine', 'tag'])->default('tag');
            $table->string('spam_folder')->default('Spam'); // For quarantine action
            $table->boolean('auto_learn')->default(true); // Bayesian auto-learning
            $table->boolean('use_bayes')->default(true); // Use Bayesian filtering
            $table->boolean('use_dcc')->default(false); // Distributed Checksum Clearinghouse
            $table->boolean('use_pyzor')->default(false); // Pyzor spam filtering
            $table->boolean('use_razor')->default(false); // Razor spam filtering
            $table->boolean('rewrite_header')->default(true); // Add [SPAM] to subject
            $table->string('spam_subject_tag')->default('[SPAM]');
            $table->integer('emails_processed')->default(0);
            $table->integer('spam_detected')->default(0);
            $table->integer('ham_detected')->default(0);
            $table->timestamps();

            $table->unique('account_id');
            $table->index('is_enabled');
        });

        // Spam Scores (Track individual email spam scores)
        Schema::create('vp_spam_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('email_from')->nullable();
            $table->string('email_to');
            $table->string('subject')->nullable();
            $table->decimal('spam_score', 5, 2); // Actual spam score
            $table->decimal('required_score', 4, 2); // Threshold at time of check
            $table->boolean('is_spam')->default(false);
            $table->enum('action_taken', ['delivered', 'deleted', 'quarantined', 'tagged'])->default('delivered');
            $table->text('tests_hit')->nullable(); // Which SpamAssassin tests were triggered
            $table->string('message_id')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('email_to');
            $table->index('is_spam');
            $table->index('created_at');
        });

        // Spam Training (Bayesian learning - ham/spam samples)
        Schema::create('vp_spam_training', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->string('email_account'); // Which email account trained this
            $table->enum('type', ['spam', 'ham']); // Spam or Ham (legitimate email)
            $table->string('message_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();
            $table->text('headers')->nullable(); // Store key headers for training
            $table->boolean('is_trained')->default(false); // Has been fed to sa-learn
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('email_account');
            $table->index('type');
            $table->index('is_trained');
        });

        // SpamAssassin Whitelist/Blacklist
        Schema::create('vp_spamassassin_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->enum('list_type', ['whitelist', 'blacklist'])->default('whitelist');
            $table->enum('entry_type', ['email', 'domain', 'ip'])->default('email');
            $table->string('value'); // Email address, domain, or IP
            $table->string('description')->nullable();
            $table->integer('hit_count')->default(0); // Number of times matched
            $table->timestamps();

            $table->index(['account_id', 'list_type']);
            $table->index('value');
        });

        // SpamAssassin Statistics (Daily aggregated statistics)
        Schema::create('vp_spamassassin_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('vp_accounts')->onDelete('cascade');
            $table->date('date');
            $table->integer('emails_scanned')->default(0);
            $table->integer('spam_detected')->default(0);
            $table->integer('ham_detected')->default(0);
            $table->integer('emails_deleted')->default(0);
            $table->integer('emails_quarantined')->default(0);
            $table->integer('emails_tagged')->default(0);
            $table->decimal('average_spam_score', 5, 2)->default(0);
            $table->decimal('average_ham_score', 5, 2)->default(0);
            $table->json('top_spam_senders')->nullable(); // Top 10 spam senders
            $table->timestamps();

            $table->unique(['account_id', 'date']);
            $table->index('date');
        });

        // Hetzner Cloud Configuration
        Schema::create('vp_hetzner_config', function (Blueprint $table) {
            $table->id();
            $table->string('api_token')->nullable(); // Encrypted API token
            $table->boolean('is_enabled')->default(false);
            $table->string('default_datacenter')->default('nbg1'); // nbg1, fsn1, hel1, ash
            $table->string('default_server_type')->default('cx11'); // Server type for auto-provision
            $table->string('default_image')->default('ubuntu-22.04'); // Default OS image
            $table->json('ssh_key_ids')->nullable(); // Array of SSH key IDs to add to servers
            $table->boolean('auto_backups')->default(false);
            $table->decimal('monthly_budget_alert', 10, 2)->nullable(); // Alert threshold
            $table->string('notification_email')->nullable();
            $table->timestamps();
        });

        // Hetzner Cloud Servers
        Schema::create('vp_hetzner_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('vp_accounts')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('vp_users')->onDelete('set null');
            $table->bigInteger('hetzner_server_id')->unique(); // Hetzner Cloud server ID
            $table->string('name');
            $table->enum('server_type', ['cx11', 'cx21', 'cx31', 'cx41', 'cx51', 'cpx11', 'cpx21', 'cpx31', 'cpx41', 'cpx51', 'ccx12', 'ccx22', 'ccx32', 'ccx42', 'ccx52']); // Hetzner server types
            $table->string('datacenter'); // nbg1-dc3, fsn1-dc14, hel1-dc2, ash-dc1
            $table->string('location'); // nbg1, fsn1, hel1, ash
            $table->string('image'); // OS image
            $table->enum('status', ['initializing', 'starting', 'running', 'stopping', 'stopped', 'migrating', 'rebuilding', 'deleting', 'deleted', 'unknown'])->default('initializing');
            $table->string('public_ipv4')->nullable();
            $table->string('public_ipv6')->nullable();
            $table->json('private_networks')->nullable(); // Array of private network IDs
            $table->integer('disk_size')->default(0); // GB
            $table->integer('vcpus')->default(0);
            $table->integer('memory')->default(0); // MB
            $table->decimal('hourly_price', 10, 4)->default(0);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->boolean('backups_enabled')->default(false);
            $table->json('labels')->nullable(); // Key-value labels
            $table->text('root_password')->nullable(); // Encrypted
            $table->timestamp('created_at_hetzner')->nullable(); // When created at Hetzner
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('hetzner_server_id');
            $table->index('account_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('location');
        });

        // Hetzner Cloud Volumes (Block Storage)
        Schema::create('vp_hetzner_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('vp_hetzner_servers')->onDelete('set null');
            $table->bigInteger('hetzner_volume_id')->unique();
            $table->string('name');
            $table->integer('size')->default(10); // GB
            $table->string('location'); // nbg1, fsn1, hel1, ash
            $table->enum('format', ['ext4', 'xfs'])->nullable();
            $table->string('linux_device')->nullable(); // e.g., /dev/disk/by-id/scsi-0HC_Volume_12345
            $table->string('mount_point')->nullable(); // e.g., /mnt/volume1
            $table->enum('status', ['creating', 'available', 'attaching', 'attached', 'detaching', 'deleting', 'deleted'])->default('creating');
            $table->boolean('auto_mount')->default(false);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->json('labels')->nullable();
            $table->timestamp('attached_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('hetzner_volume_id');
            $table->index('server_id');
            $table->index('status');
        });

        // Hetzner Cloud Snapshots
        Schema::create('vp_hetzner_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('vp_hetzner_servers')->onDelete('set null');
            $table->bigInteger('hetzner_snapshot_id')->unique();
            $table->enum('type', ['server', 'volume'])->default('server'); // snapshot type
            $table->bigInteger('source_id'); // Server ID or Volume ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('size')->default(0); // GB
            $table->enum('status', ['creating', 'available', 'deleting', 'deleted'])->default('creating');
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->json('labels')->nullable();
            $table->timestamp('created_at_hetzner')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('hetzner_snapshot_id');
            $table->index('server_id');
            $table->index('type');
            $table->index('status');
        });

        // Hetzner Cloud Billing & Cost Tracking
        Schema::create('vp_hetzner_billing', function (Blueprint $table) {
            $table->id();
            $table->enum('resource_type', ['server', 'volume', 'snapshot', 'floating_ip', 'load_balancer', 'traffic']); // Resource type
            $table->bigInteger('resource_id')->nullable(); // Server ID, Volume ID, etc.
            $table->string('resource_name')->nullable();
            $table->date('billing_date'); // Date of the charge
            $table->integer('billing_month'); // Month (1-12)
            $table->integer('billing_year'); // Year
            $table->decimal('hours_used', 10, 2)->default(0); // Hours in the billing period
            $table->decimal('hourly_rate', 10, 4)->default(0);
            $table->decimal('amount', 10, 2)->default(0); // Cost for this entry
            $table->string('currency', 3)->default('EUR');
            $table->json('details')->nullable(); // Additional billing details
            $table->timestamps();

            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('billing_date');
            $table->index(['billing_year', 'billing_month']);
        });

        // Hetzner Cloud Floating IPs
        Schema::create('vp_hetzner_floating_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained('vp_hetzner_servers')->onDelete('set null');
            $table->bigInteger('hetzner_floating_ip_id')->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address');
            $table->enum('type', ['ipv4', 'ipv6'])->default('ipv4');
            $table->string('location'); // nbg1, fsn1, hel1, ash
            $table->boolean('is_assigned')->default(false);
            $table->string('dns_ptr')->nullable(); // Reverse DNS
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->json('labels')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('hetzner_floating_ip_id');
            $table->index('server_id');
            $table->index('ip_address');
        });

        // Hetzner Cloud Firewalls
        Schema::create('vp_hetzner_firewalls', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hetzner_firewall_id')->unique();
            $table->string('name');
            $table->json('rules')->nullable(); // Firewall rules in JSON
            $table->json('applied_to')->nullable(); // Array of server IDs
            $table->json('labels')->nullable();
            $table->timestamps();

            $table->index('hetzner_firewall_id');
        });

        // Hetzner Cloud Load Balancers
        Schema::create('vp_hetzner_load_balancers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hetzner_lb_id')->unique();
            $table->string('name');
            $table->string('load_balancer_type'); // lb11, lb21, lb31
            $table->string('location');
            $table->string('public_ipv4')->nullable();
            $table->string('public_ipv6')->nullable();
            $table->json('services')->nullable(); // Load balancer services configuration
            $table->json('targets')->nullable(); // Target servers
            $table->string('algorithm')->default('round_robin'); // round_robin, least_connections
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->json('labels')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('hetzner_lb_id');
        });

        // Hetzner Cloud Private Networks
        Schema::create('vp_hetzner_networks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hetzner_network_id')->unique();
            $table->string('name');
            $table->string('ip_range'); // e.g., 10.0.0.0/16
            $table->json('subnets')->nullable(); // Array of subnets
            $table->json('routes')->nullable(); // Network routes
            $table->json('labels')->nullable();
            $table->timestamps();

            $table->index('hetzner_network_id');
        });

        // Hetzner SSH Keys
        Schema::create('vp_hetzner_ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('vp_users')->onDelete('cascade');
            $table->bigInteger('hetzner_ssh_key_id')->unique();
            $table->string('name');
            $table->text('public_key');
            $table->string('fingerprint');
            $table->json('labels')->nullable();
            $table->timestamps();

            $table->index('hetzner_ssh_key_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vp_hetzner_ssh_keys');
        Schema::dropIfExists('vp_hetzner_networks');
        Schema::dropIfExists('vp_hetzner_load_balancers');
        Schema::dropIfExists('vp_hetzner_firewalls');
        Schema::dropIfExists('vp_hetzner_floating_ips');
        Schema::dropIfExists('vp_hetzner_billing');
        Schema::dropIfExists('vp_hetzner_snapshots');
        Schema::dropIfExists('vp_hetzner_volumes');
        Schema::dropIfExists('vp_hetzner_servers');
        Schema::dropIfExists('vp_hetzner_config');
        Schema::dropIfExists('vp_spamassassin_statistics');
        Schema::dropIfExists('vp_spamassassin_lists');
        Schema::dropIfExists('vp_spam_training');
        Schema::dropIfExists('vp_spam_scores');
        Schema::dropIfExists('vp_spamassassin_config');
        Schema::dropIfExists('vp_migration_logs');
        Schema::dropIfExists('vp_migrations');
        Schema::dropIfExists('vp_webmail_sessions');
        Schema::dropIfExists('vp_ovh_failover_ips');
        Schema::dropIfExists('vp_ovh_vrack');
        Schema::dropIfExists('vp_ovh_object_storage');
        Schema::dropIfExists('vp_ovh_load_balancers');
        Schema::dropIfExists('vp_ovh_cloud_instances');
        Schema::dropIfExists('vp_ovh_billing');
        Schema::dropIfExists('vp_ovh_servers');
        Schema::dropIfExists('vp_ovh_config');
        Schema::dropIfExists('vp_webmail_config');
        Schema::dropIfExists('vp_firewall_port_scans');
        Schema::dropIfExists('vp_firewall_connections');
        Schema::dropIfExists('vp_firewall_login_failures');
        Schema::dropIfExists('vp_firewall_deny');
        Schema::dropIfExists('vp_firewall_allow');
        Schema::dropIfExists('vp_firewall_ports');
        Schema::dropIfExists('vp_firewall_rules');
        Schema::dropIfExists('vp_firewall_config');
        Schema::dropIfExists('vp_waf_statistics');
        Schema::dropIfExists('vp_waf_blacklist');
        Schema::dropIfExists('vp_waf_whitelist');
        Schema::dropIfExists('vp_waf_logs');
        Schema::dropIfExists('vp_waf_rules');
        Schema::dropIfExists('vp_waf_config');
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
        Schema::dropIfExists('vp_email_filter_actions');
        Schema::dropIfExists('vp_email_filter_conditions');
        Schema::dropIfExists('vp_email_filters');
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
