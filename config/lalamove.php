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
        'service_type' => 'MOTORCYCLE',
        'language' => 'en_MY',
        'currency' => 'MYR',
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