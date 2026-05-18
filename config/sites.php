<?php

$appUrlHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

return [
    'primary_legacy_key' => env('PRIMARY_SITE_KEY', env('PRIMARY_SITE_LEGACY_KEY', '1dollar')),
    'primary_host' => env('PRIMARY_SITE_HOST', $appUrlHost),
    'internal_portal_path' => env('INTERNAL_PORTAL_PATH', 'portal'),
    'admin_prefix' => env('ADMIN_PATH_PREFIX', 'admin'),
    'team_prefix' => env('TEAM_PATH_PREFIX', 'team'),
    'customer_portal_enabled' => filter_var(env('CUSTOMER_PORTAL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'default_customer_credit_limit' => env('DEFAULT_CUSTOMER_CREDIT_LIMIT', 0),
    'default_customer_single_order_limit' => env('DEFAULT_CUSTOMER_SINGLE_ORDER_LIMIT', 0),

    /*
    |--------------------------------------------------------------------------
    | Fallback Sites
    |--------------------------------------------------------------------------
    |
    | These values keep phase two bootable before the normalized `sites`
    | tables are installed. Once the new tables exist, SiteResolver uses
    | database records first and only falls back to this configuration.
    |
    */
    'fallback_sites' => [
        '1dollar' => [
            'legacy_key' => '1dollar',
            'slug' => '1dollar',
            'name' => '1Dollar Digitizing',
            'brand_name' => '1Dollar Digitizing',
            'host' => env('PRIMARY_SITE_HOST', $appUrlHost),
            'company_address' => env('SITE_COMPANY_ADDRESS', '46494 Mission Blvd, Fremont, California 94539'),
            'support_email' => env('SITE_SUPPORT_EMAIL', env('SITE_FROM_EMAIL', env('LEGACY_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'weborders@1dollardigitizing.com')))),
            'from_email' => env('SITE_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'weborders@1dollardigitizing.com')),
            'website_address' => env('PRIMARY_SITE_HOST', $appUrlHost),
            'is_primary' => true,
            'timezone' => env('APP_TIMEZONE', 'UTC'),
        ],
    ],
];
