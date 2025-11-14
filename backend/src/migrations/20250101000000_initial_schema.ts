import { Knex } from 'knex';

export async function up(knex: Knex): Promise<void> {
  // Create roles table
  await knex.schema.createTable('roles', (table) => {
    table.increments('id').primary();
    table.string('name', 50).notNullable().unique();
    table.string('description', 255);
    table.timestamps(true, true);
  });

  // Create permissions table
  await knex.schema.createTable('permissions', (table) => {
    table.increments('id').primary();
    table.string('name', 100).notNullable().unique();
    table.string('resource', 50).notNullable();
    table.string('action', 50).notNullable();
    table.string('description', 255);
    table.timestamps(true, true);
  });

  // Create role_permissions table
  await knex.schema.createTable('role_permissions', (table) => {
    table.increments('id').primary();
    table.integer('role_id').unsigned().notNullable().references('id').inTable('roles').onDelete('CASCADE');
    table.integer('permission_id').unsigned().notNullable().references('id').inTable('permissions').onDelete('CASCADE');
    table.unique(['role_id', 'permission_id']);
    table.timestamps(true, true);
  });

  // Create users table
  await knex.schema.createTable('users', (table) => {
    table.increments('id').primary();
    table.string('email', 255).notNullable().unique();
    table.string('password_hash', 255).notNullable();
    table.string('first_name', 100);
    table.string('last_name', 100);
    table.integer('role_id').unsigned().notNullable().references('id').inTable('roles');
    table.boolean('is_active').defaultTo(true);
    table.boolean('is_verified').defaultTo(false);
    table.string('verification_token', 255);
    table.timestamp('verification_token_expires');
    table.string('reset_password_token', 255);
    table.timestamp('reset_password_expires');
    table.boolean('two_factor_enabled').defaultTo(false);
    table.string('two_factor_secret', 255);
    table.timestamp('last_login');
    table.integer('failed_login_attempts').defaultTo(0);
    table.timestamp('locked_until');
    table.timestamps(true, true);
    table.index('email');
    table.index('role_id');
  });

  // Create sessions table
  await knex.schema.createTable('sessions', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.text('refresh_token').notNullable();
    table.string('ip_address', 45);
    table.string('user_agent', 255);
    table.timestamp('expires_at').notNullable();
    table.timestamps(true, true);
    table.index('user_id');
    table.index('expires_at');
  });

  // Create audit_logs table
  await knex.schema.createTable('audit_logs', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().references('id').inTable('users').onDelete('SET NULL');
    table.string('action', 100).notNullable();
    table.string('resource', 100).notNullable();
    table.integer('resource_id').unsigned();
    table.json('old_values');
    table.json('new_values');
    table.string('ip_address', 45);
    table.string('user_agent', 255);
    table.timestamp('created_at').defaultTo(knex.fn.now());
    table.index(['user_id', 'created_at']);
    table.index(['resource', 'resource_id']);
  });

  // Create settings table
  await knex.schema.createTable('settings', (table) => {
    table.increments('id').primary();
    table.string('key', 100).notNullable().unique();
    table.text('value').notNullable();
    table.string('type', 20).defaultTo('string'); // string, number, boolean, json
    table.string('description', 255);
    table.boolean('is_public').defaultTo(false);
    table.timestamps(true, true);
  });

  // Create quotas table
  await knex.schema.createTable('quotas', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().unique().references('id').inTable('users').onDelete('CASCADE');
    table.bigInteger('disk_space_limit').defaultTo(10737418240); // 10GB in bytes
    table.bigInteger('disk_space_used').defaultTo(0);
    table.bigInteger('bandwidth_limit').defaultTo(107374182400); // 100GB in bytes
    table.bigInteger('bandwidth_used').defaultTo(0);
    table.integer('domains_limit').defaultTo(10);
    table.integer('domains_used').defaultTo(0);
    table.integer('databases_limit').defaultTo(10);
    table.integer('databases_used').defaultTo(0);
    table.integer('email_accounts_limit').defaultTo(50);
    table.integer('email_accounts_used').defaultTo(0);
    table.integer('ftp_accounts_limit').defaultTo(10);
    table.integer('ftp_accounts_used').defaultTo(0);
    table.integer('cron_jobs_limit').defaultTo(10);
    table.integer('cron_jobs_used').defaultTo(0);
    table.timestamps(true, true);
  });

  // Create domains table
  await knex.schema.createTable('domains', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('domain_name', 255).notNullable().unique();
    table.string('document_root', 500);
    table.boolean('is_primary').defaultTo(false);
    table.string('status', 20).defaultTo('active'); // active, suspended, pending
    table.boolean('auto_ssl').defaultTo(true);
    table.boolean('force_https').defaultTo(false);
    table.string('php_version', 10).defaultTo('8.2');
    table.timestamps(true, true);
    table.index('user_id');
    table.index('domain_name');
  });

  // Create dns_zones table
  await knex.schema.createTable('dns_zones', (table) => {
    table.increments('id').primary();
    table.integer('domain_id').unsigned().notNullable().references('id').inTable('domains').onDelete('CASCADE');
    table.string('zone_file', 500);
    table.integer('serial').unsigned().notNullable();
    table.integer('refresh').unsigned().defaultTo(86400);
    table.integer('retry').unsigned().defaultTo(7200);
    table.integer('expire').unsigned().defaultTo(3600000);
    table.integer('ttl').unsigned().defaultTo(86400);
    table.timestamps(true, true);
    table.index('domain_id');
  });

  // Create dns_records table
  await knex.schema.createTable('dns_records', (table) => {
    table.increments('id').primary();
    table.integer('zone_id').unsigned().notNullable().references('id').inTable('dns_zones').onDelete('CASCADE');
    table.string('name', 255).notNullable();
    table.string('type', 10).notNullable(); // A, AAAA, CNAME, MX, TXT, SRV, CAA, etc.
    table.text('value').notNullable();
    table.integer('ttl').unsigned().defaultTo(3600);
    table.integer('priority').unsigned(); // For MX and SRV records
    table.integer('weight').unsigned(); // For SRV records
    table.integer('port').unsigned(); // For SRV records
    table.timestamps(true, true);
    table.index('zone_id');
    table.index(['zone_id', 'type']);
  });

  // Create databases table
  await knex.schema.createTable('databases', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('database_name', 255).notNullable().unique();
    table.string('database_type', 20).notNullable().defaultTo('mysql'); // mysql, postgresql
    table.bigInteger('size_bytes').defaultTo(0);
    table.timestamp('last_backup');
    table.timestamps(true, true);
    table.index('user_id');
  });

  // Create database_users table
  await knex.schema.createTable('database_users', (table) => {
    table.increments('id').primary();
    table.integer('database_id').unsigned().notNullable().references('id').inTable('databases').onDelete('CASCADE');
    table.string('username', 255).notNullable();
    table.string('host', 255).defaultTo('localhost');
    table.json('privileges');
    table.timestamps(true, true);
    table.unique(['database_id', 'username', 'host']);
    table.index('database_id');
  });

  // Create email_accounts table
  await knex.schema.createTable('email_accounts', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.integer('domain_id').unsigned().notNullable().references('id').inTable('domains').onDelete('CASCADE');
    table.string('email_address', 255).notNullable().unique();
    table.string('password_hash', 255).notNullable();
    table.bigInteger('quota_bytes').defaultTo(1073741824); // 1GB
    table.bigInteger('used_bytes').defaultTo(0);
    table.boolean('is_active').defaultTo(true);
    table.timestamps(true, true);
    table.index('user_id');
    table.index('domain_id');
  });

  // Create email_forwarders table
  await knex.schema.createTable('email_forwarders', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.integer('domain_id').unsigned().notNullable().references('id').inTable('domains').onDelete('CASCADE');
    table.string('source', 255).notNullable();
    table.string('destination', 255).notNullable();
    table.boolean('is_active').defaultTo(true);
    table.timestamps(true, true);
    table.index('user_id');
    table.index('domain_id');
  });

  // Create ssl_certificates table
  await knex.schema.createTable('ssl_certificates', (table) => {
    table.increments('id').primary();
    table.integer('domain_id').unsigned().notNullable().references('id').inTable('domains').onDelete('CASCADE');
    table.string('certificate_type', 20).defaultTo('letsencrypt'); // letsencrypt, custom
    table.text('certificate');
    table.text('private_key');
    table.text('ca_bundle');
    table.timestamp('issued_at');
    table.timestamp('expires_at');
    table.boolean('auto_renew').defaultTo(true);
    table.string('status', 20).defaultTo('active'); // active, expired, revoked
    table.timestamps(true, true);
    table.index('domain_id');
    table.index('expires_at');
  });

  // Create ftp_accounts table
  await knex.schema.createTable('ftp_accounts', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('username', 255).notNullable().unique();
    table.string('password_hash', 255).notNullable();
    table.string('home_directory', 500).notNullable();
    table.bigInteger('quota_bytes');
    table.boolean('is_active').defaultTo(true);
    table.timestamps(true, true);
    table.index('user_id');
  });

  // Create cron_jobs table
  await knex.schema.createTable('cron_jobs', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('name', 255).notNullable();
    table.text('command').notNullable();
    table.string('schedule', 100).notNullable(); // Cron expression
    table.boolean('is_active').defaultTo(true);
    table.timestamp('last_run');
    table.timestamp('next_run');
    table.text('last_output');
    table.string('last_status', 20); // success, failed
    table.timestamps(true, true);
    table.index('user_id');
    table.index(['is_active', 'next_run']);
  });

  // Create backups table
  await knex.schema.createTable('backups', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('backup_type', 20).notNullable(); // full, incremental
    table.string('status', 20).notNullable().defaultTo('pending'); // pending, running, completed, failed
    table.string('file_path', 500);
    table.bigInteger('size_bytes');
    table.json('includes'); // What was backed up
    table.timestamp('started_at');
    table.timestamp('completed_at');
    table.text('error_message');
    table.timestamps(true, true);
    table.index('user_id');
    table.index(['status', 'created_at']);
  });

  // Create firewall_rules table
  await knex.schema.createTable('firewall_rules', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().references('id').inTable('users').onDelete('CASCADE');
    table.string('rule_type', 20).notNullable(); // allow, deny
    table.string('ip_address', 45).notNullable();
    table.string('protocol', 10).defaultTo('tcp'); // tcp, udp, icmp
    table.integer('port').unsigned();
    table.string('description', 255);
    table.boolean('is_active').defaultTo(true);
    table.timestamps(true, true);
    table.index(['ip_address', 'is_active']);
  });

  // Create api_keys table
  await knex.schema.createTable('api_keys', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('name', 100).notNullable();
    table.string('key_hash', 255).notNullable().unique();
    table.json('permissions');
    table.timestamp('last_used');
    table.timestamp('expires_at');
    table.boolean('is_active').defaultTo(true);
    table.timestamps(true, true);
    table.index('user_id');
    table.index('key_hash');
  });

  // Create usage_stats table
  await knex.schema.createTable('usage_stats', (table) => {
    table.increments('id').primary();
    table.integer('user_id').unsigned().notNullable().references('id').inTable('users').onDelete('CASCADE');
    table.string('metric_type', 50).notNullable(); // cpu, memory, disk, bandwidth
    table.decimal('value', 15, 2).notNullable();
    table.string('unit', 20).notNullable();
    table.timestamp('recorded_at').defaultTo(knex.fn.now());
    table.index(['user_id', 'metric_type', 'recorded_at']);
  });
}

export async function down(knex: Knex): Promise<void> {
  await knex.schema.dropTableIfExists('usage_stats');
  await knex.schema.dropTableIfExists('api_keys');
  await knex.schema.dropTableIfExists('firewall_rules');
  await knex.schema.dropTableIfExists('backups');
  await knex.schema.dropTableIfExists('cron_jobs');
  await knex.schema.dropTableIfExists('ftp_accounts');
  await knex.schema.dropTableIfExists('ssl_certificates');
  await knex.schema.dropTableIfExists('email_forwarders');
  await knex.schema.dropTableIfExists('email_accounts');
  await knex.schema.dropTableIfExists('database_users');
  await knex.schema.dropTableIfExists('databases');
  await knex.schema.dropTableIfExists('dns_records');
  await knex.schema.dropTableIfExists('dns_zones');
  await knex.schema.dropTableIfExists('domains');
  await knex.schema.dropTableIfExists('quotas');
  await knex.schema.dropTableIfExists('settings');
  await knex.schema.dropTableIfExists('audit_logs');
  await knex.schema.dropTableIfExists('sessions');
  await knex.schema.dropTableIfExists('users');
  await knex.schema.dropTableIfExists('role_permissions');
  await knex.schema.dropTableIfExists('permissions');
  await knex.schema.dropTableIfExists('roles');
}
