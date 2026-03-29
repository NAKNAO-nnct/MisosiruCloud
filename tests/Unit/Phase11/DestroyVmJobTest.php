<?php

declare(strict_types=1);

use App\Data\Vm\VmMetaData;
use App\Jobs\DestroyVmJob;
use App\Repositories\VmMetaRepository;
use App\Services\VmService;

test('destroy vm job terminates vm from vm meta id', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 50,
        'tenant_id' => 1,
        'proxmox_vmid' => 330,
        'proxmox_node' => 'pve1',
        'label' => 'app-vm',
        'provisioning_status' => 'ready',
    ]);

    $vmMetaRepository = Mockery::mock(VmMetaRepository::class);
    $vmMetaRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(50)
        ->andReturn($vmMeta);

    $vmService = Mockery::mock(VmService::class);
    $vmService->shouldReceive('terminateVm')
        ->once()
        ->with($vmMeta);

    $job = new DestroyVmJob(50);

    $job->handle($vmMetaRepository, $vmService);

    expect(true)->toBeTrue();
});

test('destroy vm job wraps service exception with vmid context', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 77,
        'tenant_id' => 2,
        'proxmox_vmid' => 701,
        'proxmox_node' => 'pve2',
        'label' => 'db-vm',
        'provisioning_status' => 'error',
    ]);

    $vmMetaRepository = Mockery::mock(VmMetaRepository::class);
    $vmMetaRepository->shouldReceive('findByIdOrFail')
        ->once()
        ->with(77)
        ->andReturn($vmMeta);

    $vmService = Mockery::mock(VmService::class);
    $vmService->shouldReceive('terminateVm')
        ->once()
        ->with($vmMeta)
        ->andThrow(new RuntimeException('api timeout'));

    $job = new DestroyVmJob(77);

    expect(fn () => $job->handle($vmMetaRepository, $vmService))
        ->toThrow(RuntimeException::class, 'Failed to destroy VM 701: api timeout');
});
