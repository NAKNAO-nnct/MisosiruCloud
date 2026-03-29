<?php

declare(strict_types=1);

use App\Enums\VmStatus;
use App\Jobs\DestroyVmJob;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\Resources\Vm;
use App\Lib\Snippet\SnippetClient;
use App\Lib\Snippet\SnippetClientFactory;
use App\Models\DatabaseInstance;
use App\Models\Tenant;
use App\Models\VmMeta;
use App\Repositories\TenantRepository;
use App\Repositories\VmMetaRepository;
use App\Services\DbaasService;
use App\Services\VmService;

test('provision vm flow updates provisioning status to ready', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'phase11-tenant']);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('cloneVm')->once()->andReturn('UPID:pve1:clone');
    $vmResource->shouldReceive('waitForTask')->twice()->andReturn(true);
    $vmResource->shouldReceive('updateVmConfig')->once()->andReturn([]);
    $vmResource->shouldReceive('resizeVm')->once()->andReturn([]);
    $vmResource->shouldReceive('startVm')->once()->andReturn('UPID:pve1:start');

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);
    app()->instance(ProxmoxApi::class, $api);

    $tenantData = app(TenantRepository::class)->findByIdOrFail($tenant->id);

    $meta = app(VmService::class)->provisionVm($tenantData, [
        'label' => 'phase11-vm',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 9401,
        'cpu' => 2,
        'memory_mb' => 2048,
        'disk_gb' => 10,
    ]);

    expect($meta->getProvisioningStatus())->toBe(VmStatus::Ready);
});

test('provision vm flow stores provisioning error on failure', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'phase11-tenant-error']);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('cloneVm')->once()->andThrow(new RuntimeException('clone failed'));

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);
    app()->instance(ProxmoxApi::class, $api);

    $tenantData = app(TenantRepository::class)->findByIdOrFail($tenant->id);

    expect(function () use ($tenantData): void {
        app(VmService::class)->provisionVm($tenantData, [
            'label' => 'phase11-vm-error',
            'template_vmid' => 9000,
            'node' => 'pve1',
            'new_vmid' => 9402,
        ]);
    })->toThrow(RuntimeException::class, 'VM provisioning failed: clone failed');

    $vmMeta = VmMeta::query()->where('proxmox_vmid', 9402)->firstOrFail();

    expect($vmMeta->provisioning_status)->toBe(VmStatus::Error)
        ->and($vmMeta->provisioning_error)->toContain('clone failed');
});

test('destroy vm job deletes vm meta after proxmox and snippet cleanup', function (): void {
    $tenant = Tenant::factory()->create();
    $vmMeta = VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 9403,
        'proxmox_node' => 'pve1',
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('forceStopVm')->once()->with('pve1', 9403)->andReturn('UPID:pve1:stop');
    $vmResource->shouldReceive('deleteVm')->once()->with('pve1', 9403)->andReturn([]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);
    app()->instance(ProxmoxApi::class, $api);

    $snippetClient = Mockery::mock(SnippetClient::class);
    $snippetClient->shouldReceive('delete')->once()->with(9403);

    $snippetFactory = Mockery::mock(SnippetClientFactory::class);
    $snippetFactory->shouldReceive('forNodeIfConfigured')->once()->with('pve1')->andReturn($snippetClient);
    app()->instance(SnippetClientFactory::class, $snippetFactory);

    $job = new DestroyVmJob($vmMeta->id);
    $job->handle(app(VmMetaRepository::class), app(VmService::class));

    expect(VmMeta::query()->where('id', $vmMeta->id)->exists())->toBeFalse();
});

test('dbaas provision flow creates database instance in running state', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'phase11-dbaas']);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('cloneVm')->once()->andReturn('UPID:pve1:clone');
    $vmResource->shouldReceive('waitForTask')->twice()->andReturn(true);
    $vmResource->shouldReceive('updateVmConfig')->once()->andReturn([]);
    $vmResource->shouldReceive('startVm')->once()->andReturn('UPID:pve1:start');

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);
    app()->instance(ProxmoxApi::class, $api);

    $tenantData = app(TenantRepository::class)->findByIdOrFail($tenant->id);

    $db = app(DbaasService::class)->provision($tenantData, [
        'db_type' => 'mysql',
        'db_version' => '8.4',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 9501,
        'cpu' => 2,
        'memory_mb' => 2048,
    ]);

    expect($db->getStatus())->toBe('running')
        ->and($db->getTenantId())->toBe($tenant->id);

    expect(DatabaseInstance::query()->where('id', $db->getId())->exists())->toBeTrue();
    expect(VmMeta::query()->where('proxmox_vmid', 9501)->exists())->toBeTrue();
});
