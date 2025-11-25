<?php

$appEnv = env('APP_ENV', 'production');
$isLocal = $appEnv !== 'production';

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 'paths' => ['api/*', 'image-proxy'],

    // 'paths' => ['api/*'],
    // 'allowed_origins' => ['https://kawanku.app','https://www.kawanku.app'],
    // 'allowed_methods' => ['*'],
    // 'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'zoneid'],
    // 'supports_credentials' => false,

    // cors new
    'paths' => ['api/*', 'image-proxy'],
    // Environment-aware CORS
    // - Local: allow Laragon domain and localhost/127.0.0.1 with any port
    // - Production: read comma-separated origins from CORS_ALLOWED_ORIGINS in .env
    'allowed_origins' => $isLocal
        ? ['http://userfood.test']
        : array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
    'allowed_origins_patterns' => $isLocal
        ? ['/^http:\/\/localhost(:\d+)?$/', '/^http:\/\/127\.0\.0\.1(:\d+)?$/']
        : [],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,


];

// cors old
// 'paths' => ['api/*'],
// 'allowed_origins' => ['https://kawanku.app','https://www.kawanku.app'],
// 'allowed_methods' => ['*'],
// 'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'zoneid'],
// 'supports_credentials' => false,

// 'paths' => ['api/*', 'image-proxy'],
// 'allowed_origins' => ['http://userfood.test'],
// 'allowed_origins_patterns' => ['/^http:\/\/localhost(:\d+)?$/', '/^http:\/\/127\.0\.0\.1(:\d+)?$/'],
// 'allowed_methods' => ['*'],
// 'allowed_headers' => ['*'],
// 'exposed_headers' => [],
// 'max_age' => 0,
// 'supports_credentials' => false,
