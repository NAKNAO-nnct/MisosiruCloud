<?php

declare(strict_types=1);

use App\Data\VpsGateway\VpsGatewayData;
use App\Repositories\VpsGatewayRepository;
use App\Services\VpsGatewayService;

test('generateWireguardConfig returns expected format', function (): void {
    $gateway = VpsGatewayData::make([
        'id' => 10,
        'name' => 'gw-unit',
        'global_ip' => '203.0.113.20',
        'wireguard_ip' => '10.255.10.1',
        'wireguard_port' => 51820,
        'wireguard_public_key' => str_repeat('x', 43) . '=',
        'transit_wireguard_port' => 51830,
        'status' => 'active',
    ]);

    $service = new VpsGatewayService(app()->make(VpsGatewayRepository::class));
    $config = $service->generateWireguardConfig($gateway);

    expect($config)
        ->toContain('[Interface]')
        ->toContain('Address = 10.255.10.254/32')
        ->toContain('ListenPort = 51830')
        ->toContain('[Peer]')
        ->toContain('Endpoint = 203.0.113.20:51820')
        ->toContain('AllowedIPs = 10.255.10.1/32');
});
