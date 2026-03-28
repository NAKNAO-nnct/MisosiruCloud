<?php

declare(strict_types=1);

use App\Data\Dbaas\DatabaseInstanceData;
use App\Enums\DatabaseType;
use App\Enums\UserRole;
use App\Lib\Proxmox\Client as ProxmoxClient;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\BackupSchedule;
use App\Models\DatabaseInstance;
use App\Models\S3Credential;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VmMeta;
use App\Services\BackupService;
use App\Services\DbaasService;
use Illuminate\Support\Facades\Http;

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

test('DBaaS作成フローでVMプロビジョニングとDBレコード作成が行われる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    Http::fake([
        'https://pve.test:8006/api2/json/nodes/pve1/qemu/9000/clone' => Http::response([
            'data' => 'UPID:pve1:clone',
        ], 200),
        'https://pve.test:8006/api2/json/nodes/pve1/tasks/*/status' => Http::response([
            'data' => [
                'status' => 'stopped',
                'exitstatus' => 'OK',
            ],
        ], 200),
        'https://pve.test:8006/api2/json/nodes/pve1/qemu/701/config' => Http::response([
            'data' => [],
        ], 200),
        'https://pve.test:8006/api2/json/nodes/pve1/qemu/701/resize' => Http::response([
            'data' => [],
        ], 200),
        'https://pve.test:8006/api2/json/nodes/pve1/qemu/701/status/start' => Http::response([
            'data' => 'UPID:pve1:start',
        ], 200),
    ]);

    $this->app->instance(
        ProxmoxApi::class,
        new ProxmoxApi(new ProxmoxClient('https://pve.test:8006', 'root@pam!token', 'secret', false)),
    );

    $this->actingAs($admin)
        ->post(route('dbaas.store'), [
            'tenant_id' => $tenant->id,
            'db_type' => 'mysql',
            'db_version' => '8.4',
            'label' => 'db-main',
            'template_vmid' => 9000,
            'node' => 'pve1',
            'new_vmid' => 701,
            'cpu' => 2,
            'memory_mb' => 4096,
            'disk_gb' => 30,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $vmMeta = VmMeta::query()->where('proxmox_vmid', 701)->first();
    expect($vmMeta)->not->toBeNull();

    $database = DatabaseInstance::query()->where('vm_meta_id', $vmMeta->id)->first();
    expect($database)->not->toBeNull()
        ->and($database->tenant_id)->toBe($tenant->id)
        ->and($database->db_type->value)->toBe('mysql')
        ->and($database->status)->toBe('running');

    expect(BackupSchedule::query()->where('database_instance_id', $database->id)->exists())->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/qemu/9000/clone'));

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/qemu/701/status/start'));
});

test('DBaaSステータスAPIが応答する', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $service = Mockery::mock(DbaasService::class);
    $this->app->instance(DbaasService::class, $service);

    $db = DatabaseInstance::factory()->create(['tenant_id' => $tenant->id, 'status' => 'running']);

    $this->actingAs($admin)
        ->get(route('api.dbaas.status', $db->id))
        ->assertSuccessful()
        ->assertJsonPath('status', 'running');
});

test('バックアップ実行時にS3プロキシへのPUTが呼ばれる', function (): void {
    $admin = User::factory()->admin()->create();
    $database = DatabaseInstance::factory()->create();

    $credential = S3Credential::factory()->create([
        'tenant_id' => $database->tenant_id,
        'allowed_bucket' => 'dbaas-backups',
    ]);

    Http::fake([
        'http://localhost:9000/*' => Http::response([], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('dbaas.backup', $database->id))
        ->assertRedirect(route('dbaas.show', $database->id));

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
            && str_contains($request->url(), "/{$credential->allowed_bucket}/backups/{$database->id}/")
            && $request->hasHeader('X-Access-Key', $credential->access_key));
});

test('管理者はDBaaSリストアを実行できる', function (): void {
    $admin = User::factory()->admin()->create();
    $database = DatabaseInstance::factory()->create();

    $backupService = Mockery::mock(BackupService::class);
    $backupService->shouldReceive('restore')->once();
    $this->app->instance(BackupService::class, $backupService);

    $this->actingAs($admin)
        ->post(route('dbaas.restore', $database->id), [
            's3_key' => 'backups/' . $database->id . '/snapshot.sql.enc',
        ])
        ->assertRedirect(route('dbaas.show', $database->id));
});

test('一般ユーザはDBaaSリストアを実行できない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);
    $database = DatabaseInstance::factory()->create();

    $this->actingAs($user)
        ->post(route('dbaas.restore', $database->id), [
            's3_key' => 'backups/' . $database->id . '/snapshot.sql.enc',
        ])
        ->assertForbidden();
});

test('接続情報APIで復号済み認証情報を取得できる', function (): void {
    $admin = User::factory()->admin()->create();

    $database = DatabaseInstance::factory()->create([
        'admin_user' => 'dbadmin',
        'admin_password_encrypted' => 'plain-secret-password',
    ]);

    $this->actingAs($admin)
        ->get(route('dbaas.credentials', $database->id))
        ->assertSuccessful()
        ->assertJsonPath('connection.admin_user', 'dbadmin')
        ->assertJsonPath('connection.admin_password', 'plain-secret-password')
        ->assertJsonPath('connection.port', $database->port);
});
