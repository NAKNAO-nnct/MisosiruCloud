<?php

declare(strict_types=1);

use App\Data\Dbaas\DatabaseInstanceData;
use App\Enums\DatabaseType;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DbaasService;

test('非ログインユーザはDBaaS一覧にアクセスできない', function (): void {
    $this->get(route('dbaas.index'))->assertRedirect('/login');
});

test('一般ユーザはDBaaS一覧にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('dbaas.index'))
        ->assertForbidden();
});

test('管理者はDBaaS一覧にアクセスできる', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dbaas.index'))
        ->assertSuccessful()
        ->assertSee('DBaaS 一覧');
});

test('DBaaS作成でDbaasService::provisionが呼ばれる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $service = Mockery::mock(DbaasService::class);
    $service->shouldReceive('provision')
        ->once()
        ->andReturn(DatabaseInstanceData::make([
            'id' => 999,
            'tenant_id' => $tenant->id,
            'vm_meta_id' => 1,
            'db_type' => DatabaseType::Mysql,
            'db_version' => '8.4',
            'port' => 3306,
            'admin_user' => 'admin',
            'admin_password' => 'secret',
            'tenant_user' => '',
            'tenant_password' => '',
            'backup_encryption_key' => 'key',
            'status' => 'running',
        ]));

    $this->app->instance(DbaasService::class, $service);

    $this->actingAs($admin)
        ->post(route('dbaas.store'), [
            'tenant_id' => $tenant->id,
            'db_type' => 'mysql',
            'db_version' => '8.4',
            'label' => 'db-main',
            'template_vmid' => 9000,
            'node' => 'pve1',
            'new_vmid' => 555,
            'cpu' => 2,
            'memory_mb' => 2048,
            'disk_gb' => 20,
        ])
        ->assertRedirect(route('dbaas.show', 999));
});

test('DBaaSステータスAPIが応答する', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $service = Mockery::mock(DbaasService::class);
    $this->app->instance(DbaasService::class, $service);

    $db = App\Models\DatabaseInstance::factory()->create(['tenant_id' => $tenant->id, 'status' => 'running']);

    $this->actingAs($admin)
        ->get(route('api.dbaas.status', $db->id))
        ->assertSuccessful()
        ->assertJsonPath('status', 'running');
});
