<?php

declare(strict_types=1);

use App\Data\Vm\VmMetaData;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\Resources\Node;
use App\Lib\Proxmox\Resources\Vm;
use App\Lib\Snippet\SnippetClient;
use App\Lib\Snippet\SnippetClientFactory;
use App\Repositories\VmMetaRepository;
use App\Services\VmService;

test('listAllVms が複数ノードのレスポンスを正しく集約する', function (): void {
    $nodeResource = Mockery::mock(Node::class);
    $nodeResource->shouldReceive('listNodes')
        ->once()
        ->andReturn([['node' => 'node1'], ['node' => 'node2']]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('listVms')
        ->with('node1')
        ->once()
        ->andReturn([['vmid' => 100, 'name' => 'vm-a']]);
    $vmResource->shouldReceive('listVms')
        ->with('node2')
        ->once()
        ->andReturn([['vmid' => 101, 'name' => 'vm-b'], ['vmid' => 102, 'name' => 'vm-c']]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('node')->andReturn($nodeResource);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $service = new VmService($api, new VmMetaRepository());
    $vms = $service->listAllVms();

    expect($vms)->toHaveCount(3);
    expect(array_column($vms, 'vmid'))->toContain(100, 101, 102);
    // node カラムが追加されること
    expect($vms[0]['node'])->toBe('node1');
    expect($vms[1]['node'])->toBe('node2');
});

test('listAllVms がノード 0 件のとき空配列を返す', function (): void {
    $nodeResource = Mockery::mock(Node::class);
    $nodeResource->shouldReceive('listNodes')->once()->andReturn([]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('node')->andReturn($nodeResource);

    $service = new VmService($api, new VmMetaRepository());
    $vms = $service->listAllVms();

    expect($vms)->toBeEmpty();
});

test('terminateVm は snippet 削除失敗時も VM 削除を継続する', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 11,
        'tenant_id' => 1,
        'proxmox_vmid' => 330,
        'proxmox_node' => 'pve1',
        'label' => 'app-vm',
        'provisioning_status' => 'ready',
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('forceStopVm')->once()->with('pve1', 330)->andReturn('UPID:pve1:stop');
    $vmResource->shouldReceive('deleteVm')->once()->with('pve1', 330)->andReturn([]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $snippetClient = Mockery::mock(SnippetClient::class);
    $snippetClient->shouldReceive('delete')
        ->once()
        ->with('vm-330-user-data.yaml')
        ->andThrow(new RuntimeException('snippet delete failed'));

    $snippetFactory = Mockery::mock(SnippetClientFactory::class);
    $snippetFactory->shouldReceive('forNodeIfConfigured')
        ->once()
        ->with('pve1')
        ->andReturn($snippetClient);

    $vmMetaRepository = Mockery::mock(VmMetaRepository::class);
    $vmMetaRepository->shouldReceive('delete')->once()->with(11);

    $service = new VmService($api, $vmMetaRepository, $snippetFactory);
    $service->terminateVm($vmMeta);

    expect(true)->toBeTrue();
});

test('terminateVm は forceStop 失敗時も VM 削除を継続する', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 12,
        'tenant_id' => 1,
        'proxmox_vmid' => 331,
        'proxmox_node' => 'pve1',
        'label' => 'app-vm-2',
        'provisioning_status' => 'ready',
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('forceStopVm')->once()->with('pve1', 331)->andThrow(new RuntimeException('already stopped'));
    $vmResource->shouldReceive('deleteVm')->once()->with('pve1', 331)->andReturn([]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $vmMetaRepository = Mockery::mock(VmMetaRepository::class);
    $vmMetaRepository->shouldReceive('delete')->once()->with(12);

    $service = new VmService($api, $vmMetaRepository);
    $service->terminateVm($vmMeta);

    expect(true)->toBeTrue();
});

test('terminateVm は deleteVm 失敗時に DB レコード削除を行わず例外を投げる', function (): void {
    $vmMeta = VmMetaData::make([
        'id' => 13,
        'tenant_id' => 1,
        'proxmox_vmid' => 332,
        'proxmox_node' => 'pve1',
        'label' => 'app-vm-3',
        'provisioning_status' => 'ready',
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('forceStopVm')->once()->with('pve1', 332)->andReturn('UPID:pve1:stop');
    $vmResource->shouldReceive('deleteVm')->once()->with('pve1', 332)->andThrow(new RuntimeException('delete failed'));

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $vmMetaRepository = Mockery::mock(VmMetaRepository::class);
    $vmMetaRepository->shouldNotReceive('delete');

    $service = new VmService($api, $vmMetaRepository);

    expect(fn () => $service->terminateVm($vmMeta))->toThrow(RuntimeException::class, 'delete failed');
});
