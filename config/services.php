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

    'bpjs' => [
        'cons_id'     => env('BPJS_CONS_ID'),
        'cons_secret' => env('BPJS_CONS_SECRET'),
        'user_key'    => env('BPJS_USER_KEY'),
        'base_url'    => env('BPJS_BASE_URL'), // URL dasar dari API BPJS
        'kode_faskes' => env('BPJS_KODE_FASKES'), // Kode faskes rumah sakit
    ],

    'snowstorm' => [
        'url' => env('SNOWSTORM_URL', 'http://localhost:8080'),
    ],

];
