<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lalamove API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Lalamove API integration.
    | You can configure the API key, secret, base URL, and other settings here.
    |
    */

    'api_key' => env('LALAMOVE_API_KEY'),
    'secret' => env('LALAMOVE_SECRET'),
    'base_url' => env('LALAMOVE_BASE_URL', 'https://rest.sandbox.lalamove.com'),
    'version' => env('LALAMOVE_VERSION', 'v3'),
    // Use Kuala Lumpur market by default; override via env if needed
    'market' => env('LALAMOVE_MARKET', 'MY_KUL'),
    // Default webhook points to provided webhook.site URL; override via env
    'webhook_url' => env('LALAMOVE_WEBHOOK_URL', 'https://webhook.site/e3f46283-3586-464b-9bd8-41e31744b55f'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'quotations' => '/v3/quotations',
        'orders' => '/v3/orders',
        'drivers' => '/v3/drivers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'service_type' => env('LALAMOVE_SERVICE_TYPE', 'MOTORCYCLE'),
        'language' => env('LALAMOVE_LANGUAGE', 'en_MY'),
        'currency' => env('LALAMOVE_CURRENCY', 'MYR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    */
    'timeout' => [
        'connect' => 30,
        'request' => 60,
    ],
];