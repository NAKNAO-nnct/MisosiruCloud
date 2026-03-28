<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VpsGateway;

test('非ログインユーザは VPS ゲートウェイ一覧にアクセスできない', function (): void {
    $this->get(route('vps-gateways.index'))->assertRedirect('/login');
});

test('一般ユーザは VPS ゲートウェイ一覧にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('vps-gateways.index'))
        ->assertForbidden();
});

test('管理者は VPS ゲートウェイ登録時に wireguard ip と transit port が一意採番される', function (): void {
    $admin = User::factory()->admin()->create();

    $payload = [
        'name' => 'gw-1',
        'global_ip' => '198.51.100.10',
        'wireguard_port' => 51820,
        'wireguard_public_key' => str_repeat('b', 43) . '=',
        'status' => 'active',
        'purpose' => 'edge-1',
    ];

    $this->actingAs($admin)
        ->post(route('vps-gateways.store'), $payload)
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('vps-gateways.store'), [
            ...$payload,
            'name' => 'gw-2',
            'global_ip' => '198.51.100.11',
            'wireguard_public_key' => str_repeat('c', 43) . '=',
        ])
        ->assertRedirect();

    $first = VpsGateway::query()->where('name', 'gw-1')->firstOrFail();
    $second = VpsGateway::query()->where('name', 'gw-2')->firstOrFail();

    expect($first->wireguard_ip)->toBe('10.255.1.1')
        ->and($first->transit_wireguard_port)->toBe(51821)
        ->and($second->wireguard_ip)->toBe('10.255.2.1')
        ->and($second->transit_wireguard_port)->toBe(51822);
});

test('管理者は wireguard conf を確認できる', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('vps-gateways.store'), [
            'name' => 'gw-show',
            'global_ip' => '203.0.113.10',
            'wireguard_port' => 51820,
            'wireguard_public_key' => str_repeat('d', 43) . '=',
            'status' => 'active',
            'purpose' => 'show-test',
        ])
        ->assertRedirect();

    $gateway = VpsGateway::query()->where('name', 'gw-show')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('vps-gateways.show', $gateway->id))
        ->assertSuccessful()
        ->assertSee('[Interface]', false)
        ->assertSee('[Peer]', false)
        ->assertSee('Endpoint = 203.0.113.10:51820', false);
});
