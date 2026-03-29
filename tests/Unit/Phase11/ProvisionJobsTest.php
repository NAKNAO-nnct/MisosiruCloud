<?php

declare(strict_types=1);

use App\Data\Dbaas\DatabaseInstanceData;
use App\Data\Dbaas\ProvisionDbaasCommand;
use App\Data\Tenant\TenantData;
use App\Data\Vm\VmMetaData;
use App\Jobs\ProvisionDbaasJob;
use App\Jobs\ProvisionVmJob;
use App\Repositories\TenantRepository;
use App\Repositories\VmMetaRepository;
use App\Services\DbaasService;
use App\Services\VmService;
use Tests\TestCase;

uses(TestCase::class);

test('provision vm job resolves vm meta and calls vm service', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 1,
        'tenant_id' => 9,
        'proxmox_vmid' => 310,
        'proxmox_node' => 'pve1',
        'label' => 'web-01',
        'provisioning_status' => 'pending',
    ]);

    $vmMetaRepository = $this->mock(VmMetaRepository::class);
    $vmMetaRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(1)
        ->andReturn($vmMeta);

    $vmService = $this->mock(VmService::class);
    $vmService->shouldReceive('provisionVm')
        ->once()
        ->with($vmMeta, ['template_vmid' => 9000, 'node' => 'pve1']);

    $job = new ProvisionVmJob(1, ['template_vmid' => 9000, 'node' => 'pve1']);

    $job->handle($vmMetaRepository, $vmService);

    expect(true)->toBeTrue();
});

test('provision vm job wraps service exception', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 2,
        'tenant_id' => 10,
        'proxmox_vmid' => 311,
        'proxmox_node' => 'pve2',
        'label' => 'db-01',
        'provisioning_status' => 'pending',
    ]);

    $vmMetaRepository = $this->mock(VmMetaRepository::class);
    $vmMetaRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(2)
        ->andReturn($vmMeta);

    $vmService = $this->mock(VmService::class);
    $vmService->shouldReceive('provisionVm')
        ->once()
        ->with($vmMeta, ['template_vmid' => 9000, 'node' => 'pve2'])
        ->andThrow(new RuntimeException('clone failed'));

    $job = new ProvisionVmJob(2, ['template_vmid' => 9000, 'node' => 'pve2']);

    expect(fn () => $job->handle($vmMetaRepository, $vmService))
        ->toThrow(RuntimeException::class, 'Failed to provision VM (id=2): clone failed');
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
