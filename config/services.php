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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
    'maps_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'grab' => [
    'client_id' => env('GRAB_CLIENT_ID'),
    'client_secret' => env('GRAB_CLIENT_SECRET'),
    'sandbox_url' => env('GRAB_SANDBOX_URL'),
    'production_url' => env('GRAB_PRODUCTION_URL'),
    ],

   'zenpay' => [
    'base_url'     => env('ZENPAY_BASE_URL'),
    'biller_code'  => env('ZENPAY_BILLER_CODE'),
    'secret_key'   => env('ZENPAY_SECRET_KEY'),
    'callback_url' => env('ZENPAY_CALLBACK_URL'),
    'return_url'   => env('ZENPAY_RETURN_URL'),
    'decline_url'  => env('ZENPAY_DECLINE_URL'),
    ],

];
