# VirPanel Laravel Migration Guide

## Architecture Overview

VirPanel uses Laravel with a dual-panel architecture similar to cPanel/WHM:

### Port Configuration
- **WHM Admin Panel**: Port **15443** (HTTPS)
- **User Panel**: Port **15444** (HTTPS)

These ports are specifically chosen to avoid conflicts with:
- cPanel/WHM (2083/2087)
- Webmin (10000)
- Plesk (8443/8880)
- Common development ports (3000, 8000, 8080, etc.)

## Directory Structure

```
/usr/local/virpanel/              # Main installation directory
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/            # WHM controllers (port 15443)
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── AccountController.php
│   │   │   │   ├── PackageController.php
│   │   │   │   ├── ResellerController.php
│   │   │   │   └── IPAddressController.php
│   │   │   ├── User/             # User panel controllers (port 15444)
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── DomainController.php
│   │   │   │   ├── EmailController.php
│   │   │   │   ├── DatabaseController.php
│   │   │   │   ├── FTPController.php
│   │   │   │   ├── SSLController.php
│   │   │   │   ├── DNSController.php
│   │   │   │   ├── CronController.php
│   │   │   │   ├── BackupController.php
│   │   │   │   ├── StatisticsController.php
│   │   │   │   └── ApplicationInstallerController.php
│   │   │   └── Auth/
│   │   │       ├── AdminLoginController.php
│   │   │       └── UserLoginController.php
│   │   └── Middleware/
│   │       ├── AdminAuthenticate.php    # WHM auth middleware
│   │       ├── UserAuthenticate.php     # User panel auth middleware
│   │       └── PanelDetector.php        # Detects panel from port
│   ├── Models/
│   │   ├── User.php
│   │   ├── Account.php
│   │   ├── Package.php
│   │   ├── Domain.php
│   │   ├── Email.php
│   │   ├── Database.php
│   │   └── ... (all other models)
│   └── Services/
│       ├── AccountService.php
│       ├── DomainService.php
│       └── ... (business logic)
├── config/
│   └── virpanel.php              # VirPanel-specific config
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   └── views/
│       ├── admin/                # WHM views
│       │   ├── layouts/
│       │   ├── dashboard/
│       │   ├── accounts/
│       │   └── ...
│       └── user/                 # User panel views
│           ├── layouts/
│           ├── dashboard/
│           ├── domains/
│           ├── email/
│           └── ...
└── routes/
    ├── admin.php                 # WHM routes (port 15443)
    └── user.php                  # User routes (port 15444)
```

## Authentication System

### Dual Authentication Guards

**config/auth.php:**
```php
'guards' => [
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
    'user' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],

'providers' => [
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
        'table' => 'vp_users',
    ],
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
        'table' => 'vp_users',
    ],
],
```

### Panel Detection Middleware

The system detects which panel is being accessed based on the port:

```php
// app/Http/Middleware/PanelDetector.php
public function handle($request, Closure $next)
{
    $port = $request->server('SERVER_PORT');

    if ($port == 15443) {
        config(['virpanel.active_panel' => 'admin']);
        Auth::shouldUse('admin');
    } elseif ($port == 15444) {
        config(['virpanel.active_panel' => 'user']);
        Auth::shouldUse('user');
    }

    return $next($request);
}
```

## Nginx Configuration

### WHM Admin (Port 15443)

```nginx
server {
    listen 15443 ssl http2;
    listen [::]:15443 ssl http2;

    server_name _;
    root /usr/local/virpanel/public;
    index index.php;

    ssl_certificate /etc/virpanel/ssl/admin.crt;
    ssl_certificate_key /etc/virpanel/ssl/admin.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param VIRPANEL_PANEL admin;
        fastcgi_param SERVER_PORT 15443;
        include fastcgi_params;
    }
}
```

### User Panel (Port 15444)

```nginx
server {
    listen 15444 ssl http2;
    listen [::]:15444 ssl http2;

    server_name _;
    root /usr/local/virpanel/public;
    index index.php;

    ssl_certificate /etc/virpanel/ssl/user.crt;
    ssl_certificate_key /etc/virpanel/ssl/user.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param VIRPANEL_PANEL user;
        fastcgi_param SERVER_PORT 15444;
        include fastcgi_params;
    }
}
```

## Route Configuration

### Admin Routes (routes/admin.php)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin;

Route::prefix('admin')->middleware(['panel.detector', 'auth:admin'])->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index']);
    Route::resource('accounts', Admin\AccountController::class);
    Route::resource('packages', Admin\PackageController::class);
    Route::resource('resellers', Admin\ResellerController::class);
    Route::resource('ip-addresses', Admin\IPAddressController::class);
    // ... more admin routes
});
```

### User Routes (routes/user.php)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User;

Route::prefix('user')->middleware(['panel.detector', 'auth:user'])->group(function () {
    Route::get('/dashboard', [User\DashboardController::class, 'index']);
    Route::resource('domains', User\DomainController::class);
    Route::resource('email', User\EmailController::class);
    Route::resource('databases', User\DatabaseController::class);
    // ... more user routes
});
```

## Installation Process

### 1. System Requirements
- Ubuntu 22.04+ / Debian 11+ / Rocky Linux 9+
- 2GB+ RAM
- 20GB+ free disk space
- PHP 8.2+
- MySQL 8.0+ / MariaDB 10.6+
- Nginx
- Redis
- Node.js 20.x (for PM2 and app installer)

### 2. Quick Install

```bash
curl -sSL https://get.virpanel.com/install.sh | sudo bash
```

Or manual:

```bash
wget https://get.virpanel.com/install.sh
chmod +x install.sh
sudo ./install.sh
```

### 3. Access Panels

After installation:

- **WHM Admin**: `https://your-server-ip:15443`
- **User Panel**: `https://your-server-ip:15444`

## Migration from Current System

### Step 1: Export Current Data

```bash
cd /home/user/surfacepanel
php export-to-laravel.php
```

### Step 2: Install Laravel-based VirPanel

```bash
./install.sh
```

### Step 3: Import Data

```bash
php artisan virpanel:import /path/to/export.json
```

## Features Implemented

### WHM Admin Panel (Port 15443)
✅ Dashboard with server statistics
✅ Account management (create, suspend, terminate)
✅ Package management
✅ Reseller management
✅ IP address management
✅ DNS zone management
✅ SSL certificate management
✅ RESTful API with authentication
✅ Audit logging
✅ Module/plugin system
✅ Template customization

### User Panel (Port 15444)
✅ User dashboard with resource usage
✅ Domain management (addon, subdomain, parked)
✅ Email management (accounts, forwarders, autoresponders)
✅ Database management (MySQL + phpMyAdmin)
✅ FTP account management
✅ SSL/TLS certificates (Let's Encrypt)
✅ File manager
✅ DNS zone management
✅ Cron job management
✅ Backup & restore
✅ Website statistics (AWStats alternative)
✅ Application installer (WordPress, Joomla, Node.js, etc.)

## Security Features

1. **Separate Authentication**: WHM and User panels have independent auth systems
2. **Role-Based Access Control**: Root, Admin, Reseller, User roles
3. **Port Separation**: Different ports prevent cross-panel access
4. **SSL/TLS**: Mandatory HTTPS on both panels
5. **CSRF Protection**: Laravel's built-in CSRF
6. **Rate Limiting**: API and login rate limiting
7. **Audit Logging**: All administrative actions logged

## Performance Optimizations

1. **Redis Caching**: Session and cache storage
2. **Queue System**: Background job processing
3. **Optimized Queries**: Eloquent relationships and eager loading
4. **Asset Compilation**: Vite for frontend assets
5. **OPcache**: PHP opcode caching
6. **HTTP/2**: Enabled on both panels

## Support & Documentation

- Documentation: https://docs.virpanel.com
- API Reference: https://api-docs.virpanel.com
- Support: https://support.virpanel.com
- GitHub: https://github.com/virpanel/virpanel

## License

MIT License
