<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twocheckout' => [
        'seller_id' => env('TWOCHECKOUT_SELLER_ID', env('TWOCO_SELLER_ID', '1359240')),
        'secret_word' => env('TWOCHECKOUT_SECRET_WORD', ''),
        'purchase_url' => env('TWOCHECKOUT_PURCHASE_URL', 'https://www.2checkout.com/2co/buyer/purchase'),
        'simulation_enabled' => filter_var(env('TWOCHECKOUT_SIMULATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'simulation_outcome' => env('TWOCHECKOUT_SIMULATION_OUTCOME', 'success'),
        'simulation_customer_id' => env('TWOCHECKOUT_SIMULATION_CUSTOMER_ID'),
        'simulation_customer_email' => env('TWOCHECKOUT_SIMULATION_CUSTOMER_EMAIL', ''),
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1'),
        'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    'payments' => [
        'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'stripe_checkout'),
    ],

    'turnstile' => [
        'enabled' => filter_var(env('TURNSTILE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    ],

];
