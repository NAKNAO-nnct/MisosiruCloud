<?php

declare(strict_types=1);

return [
    'providers' => [
        'cloudflare' => [
            'api_token' => env('CLOUDFLARE_API_TOKEN'),
        ],
        'sakura' => [
            'base_url' => env('SAKURA_DNS_BASE_URL') ?: 'https://secure.sakura.ad.jp/cloud/zone/v1',
            'api_token' => env('SAKURA_DNS_API_TOKEN'),
            'zone' => env('SAKURA_DNS_ZONE'),
            'access_token' => env('SAKURA_DNS_ACCESS_TOKEN'),
            'access_token_secret' => env('SAKURA_DNS_ACCESS_TOKEN_SECRET'),
        ],
        'local' => [
            'zones_path' => env('COREDNS_ZONES_PATH', '/etc/coredns/zones'),
            'corefile_path' => env('COREDNS_COREFILE_PATH', '/etc/coredns/Corefile'),
            'container_name' => env('COREDNS_CONTAINER_NAME', 'dns'),
        ],
    ],
];
