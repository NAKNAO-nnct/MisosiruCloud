<?php

declare(strict_types=1);

use App\Data\Dbaas\DatabaseInstanceData;
use App\Data\Dbaas\ProvisionDbaasCommand;
use App\Data\Tenant\TenantData;
use App\Data\Vm\ProvisionVmCommand;
use App\Data\Vm\VmMetaData;
use App\Jobs\ProvisionDbaasJob;
use App\Jobs\ProvisionVmJob;
use App\Repositories\TenantRepository;
use App\Services\DbaasService;
use App\Services\VmService;

test('provision vm job resolves tenant and calls vm service', function (): void {
    $command = ProvisionVmCommand::make([
        'tenant_id' => 9,
        'label' => 'web-01',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 310,
    ]);

    $tenant = TenantData::make([
        'id' => 9,
        'uuid' => 'tenant-9',
        'name' => 'Tenant 9',
        'slug' => 'tenant-9',
        'status' => 'active',
    ]);

    $tenantRepository = Mockery::mock(TenantRepository::class);
    $tenantRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(9)
        ->andReturn($tenant);

    $vmService = Mockery::mock(VmService::class);
    $vmService->shouldReceive('provisionVm')
        ->once()
        ->with($tenant, $command->toArray())
        ->andReturn(VmMetaData::make([
            'id' => 1,
            'tenant_id' => 9,
            'proxmox_vmid' => 310,
            'proxmox_node' => 'pve1',
            'label' => 'web-01',
            'provisioning_status' => 'ready',
        ]));

    $job = new ProvisionVmJob($command);

    $job->handle($tenantRepository, $vmService);

    expect(true)->toBeTrue();
});

test('provision vm job wraps service exception', function (): void {
    $command = ProvisionVmCommand::make([
        'tenant_id' => 10,
        'label' => 'db-01',
        'template_vmid' => 9000,
        'node' => 'pve2',
        'new_vmid' => 311,
    ]);

    $tenant = TenantData::make([
        'id' => 10,
        'uuid' => 'tenant-10',
        'name' => 'Tenant 10',
        'slug' => 'tenant-10',
        'status' => 'active',
    ]);

    $tenantRepository = Mockery::mock(TenantRepository::class);
    $tenantRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(10)
        ->andReturn($tenant);

    $vmService = Mockery::mock(VmService::class);
    $vmService->shouldReceive('provisionVm')
        ->once()
        ->with($tenant, $command->toArray())
        ->andThrow(new RuntimeException('clone failed'));

    $job = new ProvisionVmJob($command);

    expect(fn () => $job->handle($tenantRepository, $vmService))
        ->toThrow(RuntimeException::class, 'Failed to provision VM for tenant 10: clone failed');
});

test('provision dbaas job resolves tenant and calls dbaas service', function (): void {
    $command = ProvisionDbaasCommand::make([
        'tenant_id' => 20,
        'db_type' => 'mysql',
        'db_version' => '8.4',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 701,
        'cpu' => 2,
        'memory_mb' => 2048,
    ]);

    $tenant = TenantData::make([
        'id' => 20,
        'uuid' => 'tenant-20',
        'name' => 'Tenant 20',
        'slug' => 'tenant-20',
        'status' => 'active',
    ]);

    $tenantRepository = Mockery::mock(TenantRepository::class);
    $tenantRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(20)
        ->andReturn($tenant);

    $dbaasService = Mockery::mock(DbaasService::class);
    $dbaasService->shouldReceive('provision')
        ->once()
        ->with($tenant, $command->toArray())
        ->andReturn(DatabaseInstanceData::make([
            'id' => 10,
            'tenant_id' => 20,
            'vm_meta_id' => 701,
            'db_type' => 'mysql',
            'db_version' => '8.4',
            'port' => 3306,
            'admin_user' => 'admin',
            'admin_password' => 'secret',
            'backup_encryption_key' => 'backup-key',
            'status' => 'running',
        ]));

    $job = new ProvisionDbaasJob($command);

    $job->handle($tenantRepository, $dbaasService);

    expect(true)->toBeTrue();
});

test('provision dbaas job wraps service exception', function (): void {
    $command = ProvisionDbaasCommand::make([
        'tenant_id' => 21,
        'db_type' => 'postgres',
        'db_version' => '17',
        'template_vmid' => 9000,
        'node' => 'pve2',
        'new_vmid' => 702,
        'cpu' => 2,
        'memory_mb' => 2048,
    ]);

    $tenant = TenantData::make([
        'id' => 21,
        'uuid' => 'tenant-21',
        'name' => 'Tenant 21',
        'slug' => 'tenant-21',
        'status' => 'active',
    ]);

    $tenantRepository = Mockery::mock(TenantRepository::class);
    $tenantRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(21)
        ->andReturn($tenant);

    $dbaasService = Mockery::mock(DbaasService::class);
    $dbaasService->shouldReceive('provision')
        ->once()
        ->with($tenant, $command->toArray())
        ->andThrow(new RuntimeException('config failed'));

    $job = new ProvisionDbaasJob($command);

    expect(fn () => $job->handle($tenantRepository, $dbaasService))
        ->toThrow(RuntimeException::class, 'Failed to provision DBaaS for tenant 21: config failed');
});
