<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'VirPanel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'https://localhost:2087'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'available_locales' => [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'pt' => 'Português',
        'ru' => 'Русский',
        'zh' => '中文',
        'ja' => '日本語',
        'ar' => 'العربية',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Application Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        \VirPanel\Core\Providers\AppServiceProvider::class,
        \VirPanel\Core\Providers\AuthServiceProvider::class,
        \VirPanel\Core\Providers\EventServiceProvider::class,
        \VirPanel\Core\Providers\RouteServiceProvider::class,
        \VirPanel\Core\Providers\DatabaseServiceProvider::class,
        \VirPanel\Core\Providers\CacheServiceProvider::class,
        \VirPanel\Core\Providers\QueueServiceProvider::class,
        \VirPanel\Core\Providers\ModuleServiceProvider::class,
        \VirPanel\Core\Providers\TemplateServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'App' => \VirPanel\Core\Facades\App::class,
        'Auth' => \VirPanel\Core\Facades\Auth::class,
        'Cache' => \VirPanel\Core\Facades\Cache::class,
        'Config' => \VirPanel\Core\Facades\Config::class,
        'DB' => \VirPanel\Core\Facades\DB::class,
        'Event' => \VirPanel\Core\Facades\Event::class,
        'Hash' => \VirPanel\Core\Facades\Hash::class,
        'Log' => \VirPanel\Core\Facades\Log::class,
        'Queue' => \VirPanel\Core\Facades\Queue::class,
        'Request' => \VirPanel\Core\Facades\Request::class,
        'Response' => \VirPanel\Core\Facades\Response::class,
        'Route' => \VirPanel\Core\Facades\Route::class,
        'Session' => \VirPanel\Core\Facades\Session::class,
        'Storage' => \VirPanel\Core\Facades\Storage::class,
        'Validator' => \VirPanel\Core\Facades\Validator::class,
        'Module' => \VirPanel\Core\Facades\Module::class,
        'Template' => \VirPanel\Core\Facades\Template::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    */
    'server' => [
        'hostname' => env('SERVER_HOSTNAME', 'localhost'),
        'primary_ip' => env('SERVER_PRIMARY_IP', '127.0.0.1'),
        'admin_email' => env('SERVER_ADMIN_EMAIL', 'admin@localhost'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ports Configuration
    |--------------------------------------------------------------------------
    */
    'ports' => [
        'whm' => 2087,
        'whm_insecure' => 2086,
        'cpanel' => 2083,
        'cpanel_insecure' => 2082,
        'webmail' => 2096,
        'webmail_insecure' => 2095,
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'base' => '/usr/local/virpanel',
        'data' => '/var/virpanel',
        'logs' => '/var/virpanel/logs',
        'cache' => '/var/virpanel/cache',
        'backups' => '/var/virpanel/backups',
        'uploads' => '/var/virpanel/uploads',
        'sessions' => '/var/virpanel/sessions',
        'modules' => '/usr/local/virpanel/modules',
        'templates' => '/usr/local/virpanel/templates',
        'scripts' => '/usr/local/virpanel/scripts',
        'home' => '/home',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'modules' => true,
        'templates' => true,
        'api' => true,
        'cli_scripts' => true,
        'auto_ssl' => true,
        'dns_clustering' => false,
        'email_filtering' => true,
        'malware_scanning' => true,
        'monitoring' => true,
        'two_factor_auth' => true,
    ],
];
