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

    'whatsapp' => [
        'url' => env('WAHA_BASE_URL', env('API_WHATSAPP_URL')),
        'key' => env('WHATSAPP_API_KEY'),
        'session' => env('WAHA_SESSION_NAME', 'byu-ferry'),
    ],

    'bpjs' => [
        'cons_id'     => env('BPJS_CONS_ID'),
        'cons_secret' => env('BPJS_CONS_SECRET'),
        'user_key'    => env('BPJS_USER_KEY'),
        'base_url'    => env('BPJS_BASE_URL'), 
        'kode_faskes' => env('BPJS_KODE_FASKES'),
        
        'antrol' => [
            'cons_id' => env('BPJS_ANTROL_CONS_ID'),
            'cons_secret' => env('BPJS_ANTROL_CONS_SECRET'),
            'user_key' => env('BPJS_ANTROL_USER_KEY'),
            'base_url' => env('BPJS_ANTROL_BASE_URL'),
        ],

        'vclaim' => [
            'cons_id' => env('BPJS_VCLAIM_CONS_ID'),
            'cons_secret' => env('BPJS_VCLAIM_CONS_SECRET'),
            'user_key' => env('BPJS_VCLAIM_USER_KEY'),
            'base_url' => env('BPJS_VCLAIM_BASE_URL'),
        ]
    ],

    'google' => [
        'places' => [
            'api_key' => env('GOOGLE_PLACES_API_KEY'),
            'place_id' => env('GOOGLE_PLACE_ID'),
        ]
    ],

    'snowstorm' => [
        'url' => env('SNOWSTORM_URL', 'http://localhost:8080'),
    ],

    'n8n' => [
        'url' => env('N8N_URL', 'http://localhost:5678'),
    ],
];
