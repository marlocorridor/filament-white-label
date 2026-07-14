<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    'navigation_group' => 'Settings',

    'navigation_sort' => 99,

    'navigation_icon' => 'heroicon-o-swatch',

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Disk and directory used for logo / favicon uploads from the settings page.
    |
    */

    'disk' => 'public',

    'directory' => 'white-label',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'key_prefix' => 'filament-white-label',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom CSS
    |--------------------------------------------------------------------------
    */

    'custom_css_max_length' => 50000,

    /*
    |--------------------------------------------------------------------------
    | Defaults (used when no DB record exists)
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'brand_name' => null,
        'brand_logo' => null,
        'dark_brand_logo' => null,
        'brand_logo_height' => '2rem',
        'favicon' => null,
        'font_family' => null,
        'colors' => [
            'primary' => null,
            'gray' => null,
            'danger' => null,
            'warning' => null,
            'success' => null,
            'info' => null,
        ],
        'layout' => [
            'top_navigation' => false,
            'topbar' => true,
            'breadcrumbs' => true,
            'sidebar_collapsible_on_desktop' => true,
            'sidebar_fully_collapsible_on_desktop' => false,
            'collapsible_navigation_groups' => true,
            'sidebar_width' => '18rem',
            'max_content_width' => null,
        ],
        'behavior' => [
            'spa' => false,
            'spa_prefetching' => false,
            'unsaved_changes_alerts' => false,
            'database_notifications' => false,
            'dark_mode' => true,
        ],
        'footer' => [
            'enabled' => false,
            'text' => null,
        ],
        'custom_css' => null,
    ],

];
