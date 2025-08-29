<?php

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

    'paths' => ['api/*'],
    'allowed_origins' => ['https://kawanku.app','https://www.kawanku.app'],
    'allowed_methods' => ['*'],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'zoneid'],
    'supports_credentials' => false,

    // 'paths' => ['api/*'],
    // 'allowed_origins' => ['http://localhost:58128/'],
    // 'allowed_headers' => [],
    // 'allowed_methods' => ['Content-Type', 'X-Requested-With', 'Authorization', 'zoneid'],
    // 'supports_credentials' => false,


];
