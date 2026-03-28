<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\Resources\Cluster as ProxmoxCluster;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    // ProxmoxApiはnull（未設定）の状態でテスト
    $this->app->instance(ProxmoxApi::class, null);
});

test('非ログインユーザはテナント一覧にアクセスできない', function (): void {
    $this->get(route('tenants.index'))->assertRedirect('/login');
});

test('一般ユーザはテナント一覧にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('tenants.index'))
        ->assertForbidden();
});

test('管理者はテナント一覧にアクセスできる', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('tenants.index'))
        ->assertSuccessful();
});

test('テナントを作成するとDBレコードとS3認証情報が作成される', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('tenants.store'), [
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
    ])->assertRedirect();

    $tenant = Tenant::where('slug', 'test-tenant')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Test Tenant')
        ->and($tenant->vni)->toBe(10000 + $tenant->id)
        ->and($tenant->vnet_name)->toBe("tenant{$tenant->id}")
        ->and($tenant->network_cidr)->toBe("10.{$tenant->id}.0.0/24")
        ->and($tenant->nomad_namespace)->toBe('test-tenant');

    expect($tenant->s3Credentials)->toHaveCount(1);
    expect($tenant->s3Credentials->first()->allowed_bucket)->toBe('dbaas-backups');
});

test('スラッグの一意性バリデーションで重複を拒否する', function (): void {
    $admin = User::factory()->admin()->create();
    Tenant::factory()->create(['slug' => 'existing-slug']);

    $this->actingAs($admin)->post(route('tenants.store'), [
        'name' => 'New Tenant',
        'slug' => 'existing-slug',
    ])->assertSessionHasErrors('slug');
});

test('無効なスラッグフォーマットを拒否する', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('tenants.store'), [
        'name' => 'New Tenant',
        'slug' => 'Invalid Slug!',
    ])->assertSessionHasErrors('slug');
});

test('テナント削除時にステータスがdeletedに変更される', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)
        ->delete(route('tenants.destroy', $tenant))
        ->assertRedirect(route('tenants.index'));

    expect($tenant->fresh()->status)->toBe(TenantStatus::Deleted);
});

test('利用可能なSDNゾーンを使ってVNetを作成できる', function (): void {
    $admin = User::factory()->admin()->create();

    config()->set('services.proxmox.sdn_zone', 'localzone');

    $cluster = Mockery::mock(ProxmoxCluster::class);
    $cluster->shouldReceive('listZones')->once()->andReturn([
        ['zone' => 'prodzone', 'type' => 'simple'],
    ]);
    $cluster->shouldReceive('createVnet')->once()->with(Mockery::on(fn (array $params): bool => ($params['zone'] ?? null) === 'prodzone'
            && str_starts_with((string) ($params['vnet'] ?? ''), 'tenant')
            && is_int($params['tag'] ?? null)))->andReturn([]);
    $cluster->shouldReceive('createSubnet')->once()->andReturn([]);
    $cluster->shouldReceive('applySdn')->once()->andReturn([]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('cluster')->andReturn($cluster);

    $this->app->instance(ProxmoxApi::class, $api);

    $this->actingAs($admin)->post(route('tenants.store'), [
        'name' => 'Zone Tenant',
        'slug' => 'zone-tenant',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(Tenant::where('slug', 'zone-tenant')->exists())->toBeTrue();
});

test('SDN apply が失敗してもテナント作成は成功する', function (): void {
    $admin = User::factory()->admin()->create();

    $cluster = Mockery::mock(ProxmoxCluster::class);
    $cluster->shouldReceive('listZones')->once()->andReturn([
        ['zone' => 'prodzone', 'type' => 'simple'],
    ]);
    $cluster->shouldReceive('createVnet')->once()->andReturn([]);
    $cluster->shouldReceive('createSubnet')->once()->andReturn([]);
    $cluster->shouldReceive('applySdn')->once()->andThrow(new RuntimeException('Not a HASH reference'));

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('cluster')->andReturn($cluster);

    $this->app->instance(ProxmoxApi::class, $api);

    $this->actingAs($admin)->post(route('tenants.store'), [
        'name' => 'Apply Fail Tenant',
        'slug' => 'apply-fail-tenant',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(Tenant::where('slug', 'apply-fail-tenant')->exists())->toBeTrue();
});
