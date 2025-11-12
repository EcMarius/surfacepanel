# VirPanel Laravel Migration

Complete Laravel-based hosting control panel with dual authentication and port-based panel separation.

## 🎯 Architecture Overview

VirPanel uses a unique dual-panel architecture similar to cPanel/WHM:

- **WHM Admin Panel**: Port **15443** (HTTPS) - For server administrators
- **User Panel**: Port **15444** (HTTPS) - For hosting account users

These ports are specifically chosen to avoid conflicts with ALL common software.

## ✅ Features

### WHM Admin Panel (Port 15443)
- ✅ Server dashboard with real-time statistics
- ✅ Account management (create, suspend, terminate)
- ✅ Package management with resource limits
- ✅ Reseller management
- ✅ IP address management
- ✅ DNS zone management
- ✅ SSL certificate management
- ✅ Server configuration
- ✅ Module/plugin system
- ✅ API token management
- ✅ Audit logging
- ✅ System-wide backups
- ✅ **MultiPHP Manager** - System-wide PHP version management
- ✅ **ModSecurity/WAF** - Web Application Firewall with OWASP Core Rule Set

### User Panel (Port 15444)
- ✅ User dashboard with resource usage
- ✅ Domain management (addon, subdomain, parked)
- ✅ Email management (accounts, forwarders, autoresponders)
- ✅ **Email Deliverability (DKIM/SPF/DMARC)** - Gmail/Yahoo 2024 compliance
- ✅ Database management (MySQL + phpMyAdmin)
- ✅ File manager with upload/download
- ✅ FTP account management
- ✅ DNS zone management
- ✅ SSL/TLS certificates (Let's Encrypt)
- ✅ Cron job management
- ✅ Backup & restore
- ✅ Website statistics (AWStats alternative)
- ✅ Application installer (WordPress, Node.js, etc.)
- ✅ **MultiPHP Manager** - Per-domain PHP version selection & PHP.ini editor
- ✅ **WAF Security Logs** - View attack logs and manage IP whitelist

## 🔐 Dual Authentication System

The system uses Laravel's multi-guard authentication:

```php
// config/auth.php
'guards' => [
    'admin' => [...],  // WHM authentication (port 15443)
    'user' => [...],   // User panel authentication (port 15444)
]
```

### How It Works:

1. **Panel Detection Middleware** (`PanelDetector.php`):
   - Detects port (15443 or 15444)
   - Sets active guard automatically
   - Routes to correct authentication system

2. **Admin Authentication** (`AdminAuthenticate.php`):
   - Checks for root/admin/reseller roles
   - Redirects unauthorized users
   - Works on port 15443

3. **User Authentication** (`UserAuthenticate.php`):
   - Checks for 'user' role only
   - Verifies account status
   - Works on port 15444

## 📁 Project Structure

```
laravel-migration/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # WHM controllers
│   │   │   ├── User/           # User panel controllers
│   │   │   └── Auth/           # Authentication controllers
│   │   └── Middleware/
│   │       ├── PanelDetector.php        # Port-based detection
│   │       ├── AdminAuthenticate.php    # WHM auth
│   │       └── UserAuthenticate.php     # User auth
│   └── Models/
│       ├── User.php
│       ├── Account.php
│       ├── Package.php
│       └── ... (20+ models)
├── config/
│   ├── virpanel.php           # VirPanel configuration
│   └── auth.php               # Dual authentication config
├── database/
│   └── migrations/
│       └── 2024_01_01_000001_create_virpanel_tables.php
└── routes/
    ├── admin.php              # WHM routes (port 15443)
    └── user.php               # User routes (port 15444)
```

## 🚀 Installation

### Prerequisites

- Ubuntu 22.04+ / Debian 11+ / Rocky Linux 9+
- 2GB+ RAM
- 20GB+ free disk space
- Root access

### Quick Install

```bash
# Download installer
curl -sSL https://raw.githubusercontent.com/virpanel/virpanel/main/install.sh -o install.sh

# Make executable
chmod +x install.sh

# Run installer
sudo ./install.sh
```

The installer will:
1. Install dependencies (PHP 8.2, MySQL, Nginx, Redis, Node.js)
2. Install Laravel
3. Configure dual-port Nginx setup
4. Generate SSL certificates
5. Configure firewall
6. Run database migrations
7. Create admin user

### Manual Installation

```bash
# 1. Install dependencies
apt-get update
apt-get install -y php8.2 php8.2-fpm mysql-server nginx redis-server nodejs npm

# 2. Install Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 3. Create Laravel project
mkdir -p /usr/local/virpanel
cd /usr/local/virpanel
composer create-project laravel/laravel .

# 4. Copy VirPanel files
cp -r /path/to/laravel-migration/* .

# 5. Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env with your settings
# DB_DATABASE=virpanel
# DB_USERNAME=root
# DB_PASSWORD=your_password
# VIRPANEL_WHM_PORT=15443
# VIRPANEL_USER_PORT=15444

# 6. Run migrations
php artisan migrate

# 7. Configure Nginx (see Nginx Configuration section)

# 8. Start services
systemctl restart php8.2-fpm
systemctl restart nginx
systemctl restart redis-server
```

## ⚙️ Nginx Configuration

### WHM Admin (Port 15443)

Create `/etc/nginx/sites-available/virpanel-whm`:

```nginx
server {
    listen 15443 ssl http2;
    listen [::]:15443 ssl http2;

    server_name _;
    root /usr/local/virpanel/public;
    index index.php;

    ssl_certificate /etc/virpanel/ssl/admin.crt;
    ssl_certificate_key /etc/virpanel/ssl/admin.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param SERVER_PORT 15443;
        include fastcgi_params;
    }
}
```

### User Panel (Port 15444)

Create `/etc/nginx/sites-available/virpanel-user`:

```nginx
server {
    listen 15444 ssl http2;
    listen [::]:15444 ssl http2;

    server_name _;
    root /usr/local/virpanel/public;
    index index.php;

    ssl_certificate /etc/virpanel/ssl/user.crt;
    ssl_certificate_key /etc/virpanel/ssl/user.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param SERVER_PORT 15444;
        include fastcgi_params;
    }
}
```

Enable sites:
```bash
ln -s /etc/nginx/sites-available/virpanel-whm /etc/nginx/sites-enabled/
ln -s /etc/nginx/sites-available/virpanel-user /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## 🔑 Creating Admin User

```php
php artisan tinker

$user = new App\Models\User();
$user->email = 'admin@example.com';
$user->username = 'admin';
$user->password = Hash::make('your-secure-password');
$user->role = 'root';
$user->status = 'active';
$user->save();
```

## 🌐 Access Panels

After installation:

- **WHM Admin**: `https://your-server-ip:15443`
- **User Panel**: `https://your-server-ip:15444`

## 📊 Database Schema

The system includes 20+ tables:

- `vp_users` - User authentication
- `vp_accounts` - Hosting accounts
- `vp_packages` - Hosting packages
- `vp_addon_domains` - Addon domains
- `vp_subdomains` - Subdomains
- `vp_email_accounts` - Email accounts
- `vp_databases` - MySQL databases
- `vp_ftp_accounts` - FTP accounts
- `vp_dns_zones` - DNS zones
- `vp_dns_records` - DNS records
- `vp_ssl_certificates` - SSL certificates
- `vp_cron_jobs` - Cron jobs
- `vp_backups` - Backup management
- `vp_installed_apps` - Installed applications
- ... and more

## 🛡️ Security Features

1. **Dual Authentication**: Separate guards for admin/user
2. **Role-Based Access Control**: Root, Admin, Reseller, User roles
3. **Port Separation**: Different ports prevent cross-panel access
4. **SSL/TLS**: Mandatory HTTPS on both panels
5. **CSRF Protection**: Laravel's built-in CSRF
6. **Rate Limiting**: API and login rate limiting
7. **Audit Logging**: All admin actions logged
8. **Password Hashing**: Argon2ID hashing

## 🔧 Configuration Files

### config/virpanel.php

```php
return [
    'whm_port' => env('VIRPANEL_WHM_PORT', 15443),
    'user_port' => env('VIRPANEL_USER_PORT', 15444),
    'database_prefix' => env('DB_PREFIX', 'vp_'),
    // ... more settings
];
```

### .env

```
APP_NAME=VirPanel
APP_ENV=production
VIRPANEL_WHM_PORT=15443
VIRPANEL_USER_PORT=15444
DB_PREFIX=vp_
```

## 📝 Development

### Creating Controllers

**Admin Controller:**
```php
namespace App\Http\Controllers\Admin;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.admin']);
    }
}
```

**User Controller:**
```php
namespace App\Http\Controllers\User;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['panel.detector', 'auth.user']);
    }
}
```

### Adding Routes

**Admin routes** (`routes/admin.php`):
```php
Route::prefix('admin')->middleware(['panel.detector', 'auth.admin'])->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index']);
});
```

**User routes** (`routes/user.php`):
```php
Route::middleware(['panel.detector', 'auth.user'])->group(function () {
    Route::get('/dashboard', [User\DashboardController::class, 'index']);
});
```

## 📚 API Documentation

API is accessible at both ports with token authentication:

- Admin API: `https://your-server:15443/api/*`
- User API: `https://your-server:15444/api/*`

Generate API token:
```php
$token = App\Models\ApiToken::create([
    'user_id' => $user->id,
    'name' => 'My API Token',
    'token' => Str::random(80),
    'permissions' => ['read', 'write'],
]);
```

Use in requests:
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" https://your-server:15443/api/accounts
```

## 🐛 Troubleshooting

### Ports not accessible

```bash
# Check firewall
ufw allow 15443/tcp
ufw allow 15444/tcp

# Check Nginx
nginx -t
systemctl status nginx

# Check if ports are listening
netstat -tlnp | grep -E '15443|15444'
```

### Authentication not working

```bash
# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Check guards
php artisan tinker
Auth::guard('admin')->check()
Auth::guard('user')->check()
```

### Database issues

```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Check connection
php artisan tinker
DB::connection()->getPdo()
```

## 📖 Documentation

- Full Documentation: https://docs.virpanel.com
- API Reference: https://api-docs.virpanel.com
- Support: https://support.virpanel.com
- GitHub: https://github.com/virpanel/virpanel

## 📄 License

MIT License - Feel free to use in your projects!

## 🤝 Contributing

Contributions welcome! Please read CONTRIBUTING.md first.

---

**VirPanel** - Modern Hosting Control Panel
