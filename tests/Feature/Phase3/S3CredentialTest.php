<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\S3Credential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\S3CredentialService;

beforeEach(function (): void {
    $this->app->instance(ProxmoxApi::class, null);
});

test('S3認証情報を追加作成できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)->post(
        route('tenants.s3-credentials.store', $tenant),
        [
            'allowed_bucket' => 'my-bucket',
            'allowed_prefix' => 'prefix/',
            'description' => 'Test cred',
        ],
    )->assertRedirect(route('tenants.s3-credentials.index', $tenant));

    expect($tenant->s3Credentials)->toHaveCount(1)
        ->and($tenant->s3Credentials->first()->allowed_bucket)->toBe('my-bucket');
});

test('S3アクセスキーはMSIRプレフィックスで始まる', function (): void {
    $service = new S3CredentialService();
    $key = $service->generateAccessKey();

    expect($key)->toStartWith('MSIR')
        ->and(mb_strlen($key))->toBe(20);
});

test('S3シークレットキーは40文字', function (): void {
    $service = new S3CredentialService();
    $secret = $service->generateSecretKey();

    expect(mb_strlen($secret))->toBe(40);
});

test('S3認証情報のローテーション', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();
    $credential = S3Credential::factory()->create(['tenant_id' => $tenant->id]);

    $oldAccessKey = $credential->access_key;

    $this->actingAs($admin)->put(
        route('tenants.s3-credentials.rotate', [$tenant, $credential]),
    )->assertRedirect();

    $credential->refresh();
    expect($credential->access_key)->not->toBe($oldAccessKey);
});

test('S3認証情報の無効化', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();
    $credential = S3Credential::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->delete(
        route('tenants.s3-credentials.destroy', [$tenant, $credential]),
    )->assertRedirect(route('tenants.s3-credentials.index', $tenant));

    expect($credential->fresh()->is_active)->toBeFalse();
});

test('管理者以外はS3管理にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('tenants.s3-credentials.index', $tenant))
        ->assertForbidden();
});
