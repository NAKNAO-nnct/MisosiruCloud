<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

it('user role defaults to tenant_member', function (): void {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::TenantMember);
});

it('admin factory state sets admin role', function (): void {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin);
    expect($user->isAdmin())->toBeTrue();
});

it('non-admin user is not admin', function (): void {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();
});

it('user can belong to tenants', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $user->tenants()->attach($tenant->id, ['role' => 'member']);

    expect($user->tenants)->toHaveCount(1);
    expect($user->tenants->first()->id)->toBe($tenant->id);
});

it('tenant has users relationship', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $tenant->users()->attach($user->id, ['role' => 'admin']);

    expect($tenant->users)->toHaveCount(1);
    expect($tenant->users->first()->pivot->role)->toBe('admin');
});
