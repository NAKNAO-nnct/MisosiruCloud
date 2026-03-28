<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Lib\Proxmox\ProxmoxApi;
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
        ->and($tenant->vnet_name)->toBe("tenant-{$tenant->id}")
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
