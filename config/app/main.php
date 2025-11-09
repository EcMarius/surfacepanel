<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'SurfacePanel'),

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
        \SurfacePanel\Core\Providers\AppServiceProvider::class,
        \SurfacePanel\Core\Providers\AuthServiceProvider::class,
        \SurfacePanel\Core\Providers\EventServiceProvider::class,
        \SurfacePanel\Core\Providers\RouteServiceProvider::class,
        \SurfacePanel\Core\Providers\DatabaseServiceProvider::class,
        \SurfacePanel\Core\Providers\CacheServiceProvider::class,
        \SurfacePanel\Core\Providers\QueueServiceProvider::class,
        \SurfacePanel\Core\Providers\ModuleServiceProvider::class,
        \SurfacePanel\Core\Providers\TemplateServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */
    'aliases' => [
        'App' => \SurfacePanel\Core\Facades\App::class,
        'Auth' => \SurfacePanel\Core\Facades\Auth::class,
        'Cache' => \SurfacePanel\Core\Facades\Cache::class,
        'Config' => \SurfacePanel\Core\Facades\Config::class,
        'DB' => \SurfacePanel\Core\Facades\DB::class,
        'Event' => \SurfacePanel\Core\Facades\Event::class,
        'Hash' => \SurfacePanel\Core\Facades\Hash::class,
        'Log' => \SurfacePanel\Core\Facades\Log::class,
        'Queue' => \SurfacePanel\Core\Facades\Queue::class,
        'Request' => \SurfacePanel\Core\Facades\Request::class,
        'Response' => \SurfacePanel\Core\Facades\Response::class,
        'Route' => \SurfacePanel\Core\Facades\Route::class,
        'Session' => \SurfacePanel\Core\Facades\Session::class,
        'Storage' => \SurfacePanel\Core\Facades\Storage::class,
        'Validator' => \SurfacePanel\Core\Facades\Validator::class,
        'Module' => \SurfacePanel\Core\Facades\Module::class,
        'Template' => \SurfacePanel\Core\Facades\Template::class,
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
        'base' => '/usr/local/surfacepanel',
        'data' => '/var/surfacepanel',
        'logs' => '/var/surfacepanel/logs',
        'cache' => '/var/surfacepanel/cache',
        'backups' => '/var/surfacepanel/backups',
        'uploads' => '/var/surfacepanel/uploads',
        'sessions' => '/var/surfacepanel/sessions',
        'modules' => '/usr/local/surfacepanel/modules',
        'templates' => '/usr/local/surfacepanel/templates',
        'scripts' => '/usr/local/surfacepanel/scripts',
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
