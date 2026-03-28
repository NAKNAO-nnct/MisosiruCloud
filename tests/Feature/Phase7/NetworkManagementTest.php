<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Lib\Proxmox\Client as ProxmoxClient;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VmMeta;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('一般ユーザはネットワーク一覧にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('networks.index'))
        ->assertForbidden();
});

test('管理者はネットワーク一覧で db と proxmox を突合表示できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Tenant A',
        'vnet_name' => 'tenant10',
        'network_cidr' => '10.10.0.0/24',
    ]);

    $this->app->instance(
        ProxmoxApi::class,
        new ProxmoxApi(new ProxmoxClient('https://pve.local:8006', 'token-id', 'token-secret', false)),
    );

    Http::fake([
        'https://pve.local:8006/api2/json/cluster/sdn/vnets' => Http::response([
            'data' => [
                ['vnet' => 'tenant10', 'zone' => 'localzone'],
            ],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->get(route('networks.index'))
        ->assertSuccessful()
        ->assertSee('Tenant A')
        ->assertSee('tenant10')
        ->assertSee('10.10.0.0/24');

    expect($tenant->fresh()->vnet_name)->toBe('tenant10');
});

test('管理者はネットワークを作成できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create([
        'vnet_name' => null,
        'vni' => null,
        'network_cidr' => null,
    ]);

    $this->app->instance(
        ProxmoxApi::class,
        new ProxmoxApi(new ProxmoxClient('https://pve.local:8006', 'token-id', 'token-secret', false)),
    );

    Http::fake([
        'https://pve.local:8006/api2/json/cluster/sdn/zones' => Http::response([
            'data' => [
                ['zone' => 'localzone'],
            ],
        ], 200),
        'https://pve.local:8006/api2/json/cluster/sdn/vnets' => Http::response(['data' => []], 200),
        'https://pve.local:8006/api2/json/cluster/sdn/vnets/*/subnets' => Http::response(['data' => []], 200),
        'https://pve.local:8006/api2/json/cluster/sdn' => Http::response(['data' => []], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('networks.store'), [
            'tenant_id' => $tenant->id,
            'network_cidr' => '10.77.0.0/24',
        ])
        ->assertRedirect(route('networks.show', $tenant->id));

    expect($tenant->fresh()->network_cidr)->toBe('10.77.0.0/24')
        ->and($tenant->fresh()->vnet_name)->toBe('tenant' . $tenant->id);
});

test('ネットワーク詳細で接続 vm 一覧を表示できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create([
        'vnet_name' => 'tenant22',
        'network_cidr' => '10.22.0.0/24',
    ]);

    VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 2201,
        'label' => 'web-01',
    ]);

    $this->app->instance(
        ProxmoxApi::class,
        new ProxmoxApi(new ProxmoxClient('https://pve.local:8006', 'token-id', 'token-secret', false)),
    );

    Http::fake([
        'https://pve.local:8006/api2/json/cluster/sdn/vnets' => Http::response([
            'data' => [
                ['vnet' => 'tenant22', 'zone' => 'localzone'],
            ],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->get(route('networks.show', $tenant->id))
        ->assertSuccessful()
        ->assertSee('2201')
        ->assertSee('web-01');
});

test('管理者はネットワークを削除できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create([
        'vnet_name' => 'tenant30',
        'vni' => 10300,
        'network_cidr' => '10.30.0.0/24',
    ]);

    $this->app->instance(
        ProxmoxApi::class,
        new ProxmoxApi(new ProxmoxClient('https://pve.local:8006', 'token-id', 'token-secret', false)),
    );

    Http::fake([
        'https://pve.local:8006/api2/json/cluster/sdn/vnets/tenant30' => Http::response(['data' => []], 200),
        'https://pve.local:8006/api2/json/cluster/sdn' => Http::response(['data' => []], 200),
    ]);

    $this->actingAs($admin)
        ->delete(route('networks.destroy', $tenant->id))
        ->assertRedirect(route('networks.index'));

    expect($tenant->fresh()->vnet_name)->toBeNull()
        ->and($tenant->fresh()->vni)->toBeNull()
        ->and($tenant->fresh()->network_cidr)->toBeNull();
});
