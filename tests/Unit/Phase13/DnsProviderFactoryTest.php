<?php

declare(strict_types=1);

use App\Lib\Dns\CloudflareDnsProvider;
use App\Lib\Dns\DnsProviderFactory;
use App\Lib\Dns\LocalDnsProvider;
use App\Lib\Dns\SakuraDnsProvider;

test('dns provider factory resolves sakura provider', function (): void {
    $factory = new DnsProviderFactory([
        'sakura' => [
            'base_url' => 'https://dns.local/v1',
            'api_token' => 'dns-token',
        ],
    ]);

    $provider = $factory->make('sakura', 'example.com');

    expect($provider)->toBeInstanceOf(SakuraDnsProvider::class);
});

test('dns provider factory resolves cloudflare provider', function (): void {
    $factory = new DnsProviderFactory([
        'cloudflare' => [
            'api_token' => 'cf-token',
        ],
    ]);

    $provider = $factory->make('cloudflare', 'example.com', 'zone-id-1');

    expect($provider)->toBeInstanceOf(CloudflareDnsProvider::class);
});

test('dns provider factory resolves local provider', function (): void {
    $factory = new DnsProviderFactory([
        'local' => [
            'zones_path' => '/tmp/coredns/zones',
            'corefile_path' => '/tmp/coredns/Corefile',
            'container_name' => 'dns',
        ],
    ]);

    $provider = $factory->make('local', 'local.override');

    expect($provider)->toBeInstanceOf(LocalDnsProvider::class);
});
