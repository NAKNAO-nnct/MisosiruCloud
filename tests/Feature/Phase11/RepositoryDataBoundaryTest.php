<?php

declare(strict_types=1);

use App\Data\Dbaas\DatabaseInstanceData;
use App\Data\User\AuthUserData;
use App\Data\Vm\VmMetaData;
use App\Enums\DatabaseType;
use App\Enums\UserRole;
use App\Enums\VmStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\DatabaseInstanceRepository;
use App\Repositories\UserRepository;
use App\Repositories\VmMetaRepository;

test('user repository create find update delete are data-driven', function (): void {
    $repository = app(UserRepository::class);

    $created = $repository->create([
        'name' => 'Phase11 User',
        'email' => 'phase11-user@example.com',
        'password' => 'password',
        'role' => UserRole::TenantMember,
    ]);

    expect($created)->toBeInstanceOf(AuthUserData::class)
        ->and($created->getName())->toBe('Phase11 User')
        ->and($created->isAdmin())->toBeFalse();

    $userModel = User::query()->where('email', 'phase11-user@example.com')->firstOrFail();

    $foundById = $repository->findById($userModel->id);
    $foundByEmail = $repository->findByEmail('phase11-user@example.com');

    expect($foundById)->toBeInstanceOf(AuthUserData::class)
        ->and($foundByEmail)->toBeInstanceOf(AuthUserData::class)
        ->and($foundById?->getName())->toBe('Phase11 User')
        ->and($foundByEmail?->getName())->toBe('Phase11 User');

    $updated = $repository->update($userModel->id, [
        'name' => 'Phase11 Admin',
        'role' => UserRole::Admin,
    ]);

    expect($updated)->toBeInstanceOf(AuthUserData::class)
        ->and($updated->getName())->toBe('Phase11 Admin')
        ->and($updated->isAdmin())->toBeTrue();

    expect($repository->delete($userModel->id))->toBeTrue();
    expect($repository->findById($userModel->id))->toBeNull();
});

test('vm meta repository create find update return vm meta data', function (): void {
    $tenant = Tenant::factory()->create();
    $repository = app(VmMetaRepository::class);

    $created = $repository->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 9021,
        'proxmox_node' => 'pve1',
        'purpose' => 'app',
        'label' => 'phase11-vm',
        'provisioning_status' => VmStatus::Pending,
    ]);

    expect($created)->toBeInstanceOf(VmMetaData::class)
        ->and($created->getProxmoxVmid())->toBe(9021)
        ->and($created->getProvisioningStatus())->toBe(VmStatus::Pending);

    $updated = $repository->update($created->getId(), [
        'provisioning_status' => VmStatus::Ready,
        'shared_ip_address' => '10.10.10.21',
    ]);

    expect($updated)->toBeInstanceOf(VmMetaData::class)
        ->and($updated->getProvisioningStatus())->toBe(VmStatus::Ready)
        ->and($updated->getSharedIpAddress())->toBe('10.10.10.21');

    $foundByVmid = $repository->findByVmidOrFail(9021);

    expect($foundByVmid)->toBeInstanceOf(VmMetaData::class)
        ->and($foundByVmid->getId())->toBe($created->getId())
        ->and($foundByVmid->getLabel())->toBe('phase11-vm');
});

test('database instance repository create find update return database data', function (): void {
    $tenant = Tenant::factory()->create();
    $vmMeta = app(VmMetaRepository::class)->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 9123,
        'proxmox_node' => 'pve2',
        'purpose' => 'mysql',
        'label' => 'phase11-db-vm',
        'provisioning_status' => VmStatus::Ready,
    ]);

    $repository = app(DatabaseInstanceRepository::class);

    $created = $repository->create([
        'tenant_id' => $tenant->id,
        'vm_meta_id' => $vmMeta->getId(),
        'db_type' => DatabaseType::Mysql,
        'db_version' => '8.4',
        'port' => 3306,
        'admin_user' => 'admin',
        'admin_password_encrypted' => 'secret',
        'tenant_user' => 'tenant',
        'tenant_password_encrypted' => 'tenant-secret',
        'backup_encryption_key_encrypted' => 'backup-secret',
        'status' => 'running',
    ]);

    expect($created)->toBeInstanceOf(DatabaseInstanceData::class)
        ->and($created->getDbType())->toBe(DatabaseType::Mysql)
        ->and($created->getStatus())->toBe('running');

    $updated = $repository->update($created->getId(), ['status' => 'stopped']);

    expect($updated)->toBeInstanceOf(DatabaseInstanceData::class)
        ->and($updated->getStatus())->toBe('stopped');

    $found = $repository->findByIdOrFail($created->getId());

    expect($found)->toBeInstanceOf(DatabaseInstanceData::class)
        ->and($found->getVmMetaId())->toBe($vmMeta->getId())
        ->and($found->getDbVersion())->toBe('8.4');
});
