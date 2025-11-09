# VirPanel

**Modern Web Hosting Control Panel - Alternative to cPanel/WHM**

Version: 0.1.0

## Overview

VirPanel is a comprehensive, modern web hosting control panel designed as a powerful alternative to cPanel/WHM. Built with extensibility and performance in mind, it provides everything needed to manage web hosting servers, with support for future expansions like game servers and virtualization.

## Features

### Core Features
- ✅ **Complete WHM & cPanel Equivalent** - Full-featured admin and user panels
- ✅ **Module System** - Extensible architecture with uploadable modules
- ✅ **Template System** - Customizable themes for admin and user interfaces
- ✅ **Comprehensive API** - RESTful API with authentication and rate limiting
- ✅ **CLI Scripts** - Complete suite of cPanel-compatible command-line tools
- ✅ **Modern Tech Stack** - PHP 8.1+, React 18, Tailwind CSS, Symfony components
- ✅ **License Management** - Built-in licensing and verification
- ✅ **Database System** - Complete schema with 30+ tables, migrations, and seeders
- ✅ **Auto-Downloading Installer** - Downloads from license server if files missing

### Account Management
- Create, suspend, unsuspend, and remove accounts
- Disk quota and bandwidth monitoring
- Package-based resource limits
- Reseller support with white-label capabilities
- Shell/SSH access control per account
- Per-account isolation

### Domain Management
- Main, addon, parked, and subdomain support
- SSL/TLS certificate management
- AutoSSL with Let's Encrypt
- DNS zone and record management
- Domain redirects and URL forwarding
- DNSSEC support

### Email System
- Unlimited email accounts per package
- Email forwarders and autoresponders
- Spam filtering (SpamAssassin integration planned)
- DKIM, SPF, DMARC support (planned)
- Webmail interface (planned)
- Email quotas and limits

### Database Management
- MySQL and PostgreSQL support
- Database user management
- Per-database permissions
- Remote database access
- phpMyAdmin integration (planned)

### File Management
- FTP account management
- File manager interface (planned)
- Disk usage monitoring
- Backup and restore functionality
- File permissions management (planned)

### Security
- Two-factor authentication support
- API token authentication
- Rate limiting and CORS
- SSL certificate management
- Firewall configuration (planned)
- Intrusion detection (planned)

### Cloudflare Integration
- Account-level Cloudflare management
- DNS synchronization
- Proxy settings
- Auto-sync capabilities

## Installation

### System Requirements

**Minimum (VPS):**
- 512 MB RAM
- 10 GB disk space
- 1 CPU core

**Recommended (Standalone):**
- 2 GB+ RAM
- 50 GB+ disk space
- 2+ CPU cores

**Supported Operating Systems:**
- Ubuntu 20.04+
- Debian 10+
- CentOS 7+
- Rocky Linux 8+
- AlmaLinux 8+

### Quick Installation

```bash
# Download installer
wget https://license.virpanel.com/install.sh
# Or use curl
curl -O https://license.virpanel.com/install.sh

# Run installer with your license key
sudo bash install.sh --license=YOUR_LICENSE_KEY

# Or run interactive installation
sudo bash install.sh
```

The installer will:
1. Check system requirements
2. Download files from license server if not present
3. Install all dependencies (PHP, MySQL, Redis, Nginx)
4. Configure the database
5. Run migrations and seed initial data
6. Configure web server and firewall
7. Display access information

### Post-Installation

After installation completes:

1. Access WHM panel: `http://your-server-ip:2087`
2. Login with default credentials:
   - Username: `root`
   - Password: `virpanel`
3. **Change the default password immediately!**
4. Configure your license in Settings > License
5. Set up nameservers
6. Create your first hosting package
7. Create your first account

## CLI Scripts

VirPanel includes cPanel-compatible CLI scripts:

### Account Management

```bash
# Create account
createacct --username=john --domain=example.com --email=john@example.com --package=professional

# Remove account
removeacct john
removeacct jane --force  # Skip confirmation

# Suspend account
suspendacct john "Non-payment"

# Unsuspend account
unsuspendacct john

# List accounts
listaccts
listaccts --status=active
listaccts --format=csv > accounts.csv
listaccts --format=json
```

### License Management

```bash
# Check license status
checklicense
checklicense --verbose  # Detailed information
```

### Database Management

```bash
# Run migrations
php scripts/migrate.php
php scripts/migrate.php --seed  # Include seed data
php scripts/migrate.php --fresh --seed  # Fresh install

# Rollback migrations
php scripts/migrate.php --rollback
php scripts/migrate.php --rollback=3  # Rollback 3 migrations
```

## API Documentation

### Authentication

All API requests require authentication via Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_API_TOKEN" \
     https://your-server/api/v1/accounts
```

### Endpoints

#### System
- `GET /api/v1/info` - System information (public)
- `GET /api/v1/health` - Health check (public)
- `GET /api/v1/system/stats` - System statistics
- `GET /api/v1/system/packages` - List packages
- `GET /api/v1/system/settings` - Get settings
- `PUT /api/v1/system/settings/{key}` - Update setting
- `GET /api/v1/system/license` - License information

#### Accounts
- `GET /api/v1/accounts` - List accounts
- `GET /api/v1/accounts/{id}` - Get account
- `POST /api/v1/accounts` - Create account
- `PUT /api/v1/accounts/{id}` - Update account
- `DELETE /api/v1/accounts/{id}` - Delete account
- `POST /api/v1/accounts/{id}/suspend` - Suspend account
- `POST /api/v1/accounts/{id}/unsuspend` - Unsuspend account

#### Domains
- `GET /api/v1/accounts/{accountId}/domains` - List account domains
- `GET /api/v1/domains/{id}` - Get domain
- `POST /api/v1/accounts/{accountId}/domains` - Add domain
- `PUT /api/v1/domains/{id}` - Update domain
- `DELETE /api/v1/domains/{id}` - Delete domain
- `POST /api/v1/domains/{id}/ssl/enable` - Enable SSL
- `POST /api/v1/domains/{id}/ssl/disable` - Disable SSL

## Development

### Project Structure

```
virpanel/
├── config/          # Configuration files
│   ├── app/        # Application config
│   ├── api/        # API routes
│   └── template.php # Template config
├── public/         # Public web root
│   └── themes/     # Template themes
├── resources/      # Frontend resources
│   └── views/      # Twig templates
├── scripts/        # CLI scripts
├── src/
│   ├── api/        # API controllers & middleware
│   ├── cli/        # CLI command framework
│   ├── core/       # Core application
│   ├── database/   # Migrations & schema
│   ├── models/     # Data models
│   └── modules/    # Loadable modules
└── install.sh      # Installation script
```

### Module Development

Create a new module:

```php
<?php
namespace VirPanel\Modules\YourModule;

use VirPanel\Core\Contracts\ModuleInterface;

class YourModule implements ModuleInterface
{
    public function register(): void
    {
        // Register routes, services, etc.
    }

    public function boot(): void
    {
        // Boot module logic
    }

    public function getName(): string
    {
        return 'Your Module Name';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Module description';
    }
}
```

Create `module.json`:

```json
{
    "name": "YourModule",
    "version": "1.0.0",
    "class": "VirPanel\\Modules\\YourModule\\YourModule",
    "enabled": true,
    "dependencies": {},
    "permissions": ["read", "write"],
    "hooks": ["user.created"]
}
```

### Template Development

Create a new theme in `public/themes/your-theme/`:

```json
{
    "name": "your-theme",
    "display_name": "Your Theme Name",
    "version": "1.0.0",
    "type": "both",
    "author": "Your Name"
}
```

## Database Schema

### Core Tables
- `users` - WHM admin users and resellers
- `accounts` - cPanel user accounts
- `packages` - Hosting packages/plans
- `reseller_settings` - Reseller configurations

### Domain & DNS
- `domains` - All domain types
- `dns_zones` - DNS zone files
- `dns_records` - DNS records (A, AAAA, CNAME, MX, etc.)

### Email
- `email_accounts` - Email addresses
- `email_forwarders` - Email forwarding rules

### Database
- `databases` - MySQL/PostgreSQL databases
- `database_users` - Database users
- `database_permissions` - User permissions

### Security & SSL
- `ssl_certificates` - SSL/TLS certificates
- `api_tokens` - API authentication tokens

### System
- `modules` - Installed modules
- `templates` - Installed themes
- `licenses` - License information
- `settings` - System settings
- `audit_logs` - Audit trail
- `backups` - Backup records
- `cron_jobs` - Scheduled tasks

## Configuration

Key configuration files:

- `.env` - Environment variables and secrets
- `config/app/main.php` - Application settings
- `config/app/database.php` - Database configuration
- `config/template.php` - Template system settings
- `config/api/routes.php` - API route definitions

## Security

VirPanel implements multiple security layers:

- Password hashing with Argon2ID
- API token authentication
- Rate limiting (100 requests/minute by default)
- CORS protection
- Input validation and sanitization
- SQL injection prevention (prepared statements)
- XSS protection
- CSRF protection (planned)
- Two-factor authentication support

## Performance

- Redis caching for sessions and data
- Template caching with Twig
- Optimized database queries with indexes
- Connection pooling
- Asset minification (planned)
- CDN support (planned)

## Roadmap

### Phase 2: Enhanced Features
- Advanced email management (BIMI, DMARC)
- File manager interface
- Database management UI
- Advanced backup system
- Auto-update system

### Phase 3: Advanced Features
- Multi-server management
- DNS clustering
- Load balancing
- Database replication
- Monitoring and alerts

### Phase 4: Expansion
- Game server management
- Virtualization support
- Container orchestration
- Kubernetes integration

## Support

- **Documentation:** https://docs.virpanel.com
- **Email:** team@virpanel.com
- **Forum:** https://forum.virpanel.com
- **Discord:** https://discord.gg/virpanel

## License

Proprietary - Commercial license required for production use

## Credits

Developed by the VirPanel Team

Special thanks to all contributors and the open-source community.

---

**Note:** VirPanel is currently in active development. Features and API may change.
