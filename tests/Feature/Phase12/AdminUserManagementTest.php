<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

test('ユーザ一覧にロールとテナント情報が表示される', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['name' => 'Tenant Alpha']);
    $user = User::factory()->create([
        'name' => 'Tenant Admin User',
        'email' => 'tenant-admin@example.com',
        'role' => UserRole::TenantAdmin,
    ]);
    $user->tenants()->attach($tenant->id, ['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertSuccessful()
        ->assertSee('Tenant Admin User')
        ->assertSee('tenant_admin')
        ->assertSee('Tenant Alpha');
});

test('ユーザ作成時にemail一意性バリデーションが効く', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'dup@example.com']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Duplicate Email User',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::TenantMember->value,
        ])
        ->assertSessionHasErrors(['email']);
});

test('admin以外はユーザ管理へアクセスできない', function (): void {
    $tenantMember = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($tenantMember)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('ユーザロール変更が反映される', function (): void {
    $admin = User::factory()->admin()->create();
    $targetUser = User::factory()->create([
        'role' => UserRole::TenantMember,
        'email' => 'role-change@example.com',
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $targetUser->id), [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role' => UserRole::TenantAdmin->value,
            'tenant_ids' => [],
        ])
        ->assertRedirect(route('users.index'));

    $targetUser->refresh();

    expect($targetUser->role)->toBe(UserRole::TenantAdmin);
});
