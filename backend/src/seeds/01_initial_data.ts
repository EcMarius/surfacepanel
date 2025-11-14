import { Knex } from 'knex';
import { hashPassword } from '../utils/password';
import { config } from '../config';

export async function seed(knex: Knex): Promise<void> {
  // Insert roles
  await knex('roles').del();
  const [adminRole, resellerRole, userRole] = await knex('roles').insert([
    { id: 1, name: 'admin', description: 'System administrator with full access' },
    { id: 2, name: 'reseller', description: 'Reseller with ability to create users' },
    { id: 3, name: 'user', description: 'Regular user with limited access' },
  ]).returning('*');

  // Insert permissions
  await knex('permissions').del();
  const permissions = await knex('permissions').insert([
    // User management
    { name: 'users.create', resource: 'users', action: 'create', description: 'Create new users' },
    { name: 'users.read', resource: 'users', action: 'read', description: 'View users' },
    { name: 'users.update', resource: 'users', action: 'update', description: 'Update users' },
    { name: 'users.delete', resource: 'users', action: 'delete', description: 'Delete users' },

    // Domain management
    { name: 'domains.create', resource: 'domains', action: 'create', description: 'Create domains' },
    { name: 'domains.read', resource: 'domains', action: 'read', description: 'View domains' },
    { name: 'domains.update', resource: 'domains', action: 'update', description: 'Update domains' },
    { name: 'domains.delete', resource: 'domains', action: 'delete', description: 'Delete domains' },

    // DNS management
    { name: 'dns.create', resource: 'dns', action: 'create', description: 'Create DNS records' },
    { name: 'dns.read', resource: 'dns', action: 'read', description: 'View DNS records' },
    { name: 'dns.update', resource: 'dns', action: 'update', description: 'Update DNS records' },
    { name: 'dns.delete', resource: 'dns', action: 'delete', description: 'Delete DNS records' },

    // Database management
    { name: 'databases.create', resource: 'databases', action: 'create', description: 'Create databases' },
    { name: 'databases.read', resource: 'databases', action: 'read', description: 'View databases' },
    { name: 'databases.update', resource: 'databases', action: 'update', description: 'Update databases' },
    { name: 'databases.delete', resource: 'databases', action: 'delete', description: 'Delete databases' },

    // Email management
    { name: 'email.create', resource: 'email', action: 'create', description: 'Create email accounts' },
    { name: 'email.read', resource: 'email', action: 'read', description: 'View email accounts' },
    { name: 'email.update', resource: 'email', action: 'update', description: 'Update email accounts' },
    { name: 'email.delete', resource: 'email', action: 'delete', description: 'Delete email accounts' },

    // SSL management
    { name: 'ssl.create', resource: 'ssl', action: 'create', description: 'Create SSL certificates' },
    { name: 'ssl.read', resource: 'ssl', action: 'read', description: 'View SSL certificates' },
    { name: 'ssl.update', resource: 'ssl', action: 'update', description: 'Update SSL certificates' },
    { name: 'ssl.delete', resource: 'ssl', action: 'delete', description: 'Delete SSL certificates' },

    // File management
    { name: 'files.create', resource: 'files', action: 'create', description: 'Upload files' },
    { name: 'files.read', resource: 'files', action: 'read', description: 'View files' },
    { name: 'files.update', resource: 'files', action: 'update', description: 'Update files' },
    { name: 'files.delete', resource: 'files', action: 'delete', description: 'Delete files' },

    // FTP management
    { name: 'ftp.create', resource: 'ftp', action: 'create', description: 'Create FTP accounts' },
    { name: 'ftp.read', resource: 'ftp', action: 'read', description: 'View FTP accounts' },
    { name: 'ftp.update', resource: 'ftp', action: 'update', description: 'Update FTP accounts' },
    { name: 'ftp.delete', resource: 'ftp', action: 'delete', description: 'Delete FTP accounts' },

    // Cron management
    { name: 'cron.create', resource: 'cron', action: 'create', description: 'Create cron jobs' },
    { name: 'cron.read', resource: 'cron', action: 'read', description: 'View cron jobs' },
    { name: 'cron.update', resource: 'cron', action: 'update', description: 'Update cron jobs' },
    { name: 'cron.delete', resource: 'cron', action: 'delete', description: 'Delete cron jobs' },

    // Backup management
    { name: 'backups.create', resource: 'backups', action: 'create', description: 'Create backups' },
    { name: 'backups.read', resource: 'backups', action: 'read', description: 'View backups' },
    { name: 'backups.restore', resource: 'backups', action: 'restore', description: 'Restore backups' },
    { name: 'backups.delete', resource: 'backups', action: 'delete', description: 'Delete backups' },

    // Settings management
    { name: 'settings.read', resource: 'settings', action: 'read', description: 'View settings' },
    { name: 'settings.update', resource: 'settings', action: 'update', description: 'Update settings' },

    // System management
    { name: 'system.read', resource: 'system', action: 'read', description: 'View system info' },
    { name: 'system.update', resource: 'system', action: 'update', description: 'Update system config' },
  ]).returning('*');

  // Assign all permissions to admin role
  await knex('role_permissions').del();
  const adminPermissions = permissions.map(perm => ({
    role_id: 1, // admin
    permission_id: perm.id,
  }));
  await knex('role_permissions').insert(adminPermissions);

  // Assign limited permissions to reseller role
  const resellerPermissionNames = [
    'users.create', 'users.read', 'users.update',
    'domains.create', 'domains.read', 'domains.update', 'domains.delete',
    'dns.create', 'dns.read', 'dns.update', 'dns.delete',
    'databases.create', 'databases.read', 'databases.update', 'databases.delete',
    'email.create', 'email.read', 'email.update', 'email.delete',
    'ssl.create', 'ssl.read', 'ssl.update', 'ssl.delete',
    'files.create', 'files.read', 'files.update', 'files.delete',
    'ftp.create', 'ftp.read', 'ftp.update', 'ftp.delete',
    'cron.create', 'cron.read', 'cron.update', 'cron.delete',
    'backups.create', 'backups.read', 'backups.restore',
  ];
  const resellerPermissions = permissions
    .filter(perm => resellerPermissionNames.includes(perm.name))
    .map(perm => ({
      role_id: 2, // reseller
      permission_id: perm.id,
    }));
  await knex('role_permissions').insert(resellerPermissions);

  // Assign user permissions to user role
  const userPermissionNames = [
    'domains.read', 'domains.update',
    'dns.create', 'dns.read', 'dns.update', 'dns.delete',
    'databases.create', 'databases.read', 'databases.update', 'databases.delete',
    'email.create', 'email.read', 'email.update', 'email.delete',
    'ssl.read',
    'files.create', 'files.read', 'files.update', 'files.delete',
    'ftp.create', 'ftp.read', 'ftp.update', 'ftp.delete',
    'cron.create', 'cron.read', 'cron.update', 'cron.delete',
    'backups.create', 'backups.read', 'backups.restore',
  ];
  const userPermissions = permissions
    .filter(perm => userPermissionNames.includes(perm.name))
    .map(perm => ({
      role_id: 3, // user
      permission_id: perm.id,
    }));
  await knex('role_permissions').insert(userPermissions);

  // Create default admin user
  await knex('users').del();
  const adminPasswordHash = await hashPassword(config.admin.defaultPassword);
  const [adminUser] = await knex('users').insert({
    email: config.admin.defaultEmail,
    password_hash: adminPasswordHash,
    first_name: 'System',
    last_name: 'Administrator',
    role_id: 1, // admin
    is_active: true,
    is_verified: true,
  }).returning('*');

  // Create quota for admin user
  await knex('quotas').insert({
    user_id: adminUser.id,
    disk_space_limit: -1, // unlimited
    bandwidth_limit: -1, // unlimited
    domains_limit: -1,
    databases_limit: -1,
    email_accounts_limit: -1,
    ftp_accounts_limit: -1,
    cron_jobs_limit: -1,
  });

  // Insert default settings
  await knex('settings').del();
  await knex('settings').insert([
    { key: 'site_name', value: 'SurfacePanel', type: 'string', is_public: true, description: 'Site name' },
    { key: 'site_url', value: config.frontendUrl, type: 'string', is_public: true, description: 'Site URL' },
    { key: 'contact_email', value: config.admin.defaultEmail, type: 'string', is_public: true, description: 'Contact email' },
    { key: 'enable_registration', value: 'false', type: 'boolean', is_public: true, description: 'Allow user registration' },
    { key: 'require_email_verification', value: 'true', type: 'boolean', is_public: false, description: 'Require email verification' },
    { key: 'max_login_attempts', value: '5', type: 'number', is_public: false, description: 'Max failed login attempts' },
    { key: 'lockout_duration', value: '900', type: 'number', is_public: false, description: 'Account lockout duration in seconds' },
    { key: 'default_disk_quota', value: '10737418240', type: 'number', is_public: false, description: 'Default disk quota in bytes (10GB)' },
    { key: 'default_bandwidth_quota', value: '107374182400', type: 'number', is_public: false, description: 'Default bandwidth quota in bytes (100GB)' },
    { key: 'backup_enabled', value: 'true', type: 'boolean', is_public: false, description: 'Enable automated backups' },
    { key: 'backup_schedule', value: '0 2 * * *', type: 'string', is_public: false, description: 'Backup schedule (cron expression)' },
    { key: 'backup_retention_days', value: '30', type: 'number', is_public: false, description: 'Backup retention period in days' },
  ]);
}
