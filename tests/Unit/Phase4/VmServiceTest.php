<?php

declare(strict_types=1);

use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\Resources\Node;
use App\Lib\Proxmox\Resources\Vm;
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

    $service = new VmService($api);
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

    $service = new VmService($api);
    $vms = $service->listAllVms();

    expect($vms)->toBeEmpty();
});
