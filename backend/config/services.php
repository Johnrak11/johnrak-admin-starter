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
    ],

];
