<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The default theme to use for rendering views
    |
    */
    'default_theme' => env('TEMPLATE_THEME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Cache Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable template caching
    |
    */
    'cache_enabled' => env('TEMPLATE_CACHE', true),

    /*
    |--------------------------------------------------------------------------
    | Template Paths
    |--------------------------------------------------------------------------
    |
    | Additional template paths to load
    |
    */
    'paths' => [
        base_path('resources/views'),
        base_path('public/themes'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Theme Switching
    |--------------------------------------------------------------------------
    |
    | Allow users to select their own theme
    |
    */
    'allow_user_themes' => true,

    /*
    |--------------------------------------------------------------------------
    | Admin Theme
    |--------------------------------------------------------------------------
    |
    | Separate theme for admin panel
    |
    */
    'admin_theme' => env('ADMIN_THEME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Template file extensions allowed
    |
    */
    'extensions' => [
        'twig',
        'html.twig',
    ],
];
