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

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],
    'bakong' => [
        'base_url' => env('BAKONG_BASE_URL'),
        'registered_email' => env('BAKONG_REGISTERED_EMAIL'),
        'access_token' => env('BAKONG_ACCESS_TOKEN'),
        'tunnel_enabled' => env('BAKONG_TUNNEL_ENABLED', false),
        'tunnel_ip' => env('BAKONG_TUNNEL_IP', '172.19.0.1'),
        'tunnel_port' => env('BAKONG_TUNNEL_PORT', 9000),
        'proxy_url' => env('BAKONG_PROXY_URL'),
    ],

];
