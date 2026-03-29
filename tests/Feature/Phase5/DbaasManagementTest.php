<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\ProvisionDbaasJob;
use App\Models\DatabaseInstance;
use App\Models\S3Credential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DbaasService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

test('DBaaS作成時にProvisionDbaasJobがキュー投入される', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();
    Queue::fake();

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
        ->assertRedirect(route('dbaas.index'));

    Queue::assertPushed(ProvisionDbaasJob::class);
});

test('DBaaS作成リクエストで即時にDBレコードは作成されない', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();
    Queue::fake();

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
        ->assertRedirect(route('dbaas.index'));

    expect(DatabaseInstance::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
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
