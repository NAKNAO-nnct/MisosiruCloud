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

    'proxmox' => [
        'sdn_zone' => env('PROXMOX_SDN_ZONE'),
    ],

    'nomad' => [
        'address' => env('NOMAD_ADDR'),
        'token' => env('NOMAD_TOKEN'),
        'verify_tls' => env('NOMAD_VERIFY_TLS', false),
        'datacenter' => env('NOMAD_DATACENTER', 'dc1'),
    ],

    'dns' => [
        'sakura' => [
            'base_url' => env('SAKURA_DNS_BASE_URL', 'https://secure.sakura.ad.jp/cloud/zone/v1'),
            'api_token' => env('SAKURA_DNS_API_TOKEN'),
            'zone' => env('SAKURA_DNS_ZONE'),
        ],
    ],

];
