<?php

declare(strict_types=1);

use App\Lib\Proxmox\DataObjects\NodeStatus;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\ProxmoxApiFactory;
use App\Lib\Proxmox\Resources\Node;
use App\Models\ProxmoxNode;
use App\Models\User;

test('ダッシュボードにクラスタ情報が表示される', function (): void {
    $admin = User::factory()->admin()->create();

    $nodeResourceA = Mockery::mock(Node::class);
    $nodeResourceA->shouldReceive('listNodes')
        ->once()
        ->andReturn([
            ['node' => 'pve1'],
        ]);

    $nodeResourceA->shouldReceive('getNodeStatus')
        ->with('pve1')
        ->once()
        ->andReturn(new NodeStatus(
            node: 'pve1',
            status: 'online',
            cpu: 0.2,
            cpuinfo: [],
            mem: 0,
            maxmem: 0,
            disk: 0,
            maxdisk: 0,
        ));

    $nodeResourceB = Mockery::mock(Node::class);
    $nodeResourceB->shouldReceive('listNodes')
        ->once()
        ->andReturn([
            ['node' => 'pve2'],
        ]);

    $nodeResourceB->shouldReceive('getNodeStatus')
        ->with('pve2')
        ->once()
        ->andReturn(new NodeStatus(
            node: 'pve2',
            status: 'online',
            cpu: 0.4,
            cpuinfo: [],
            mem: 0,
            maxmem: 0,
            disk: 0,
            maxdisk: 0,
        ));

    $apiA = Mockery::mock(ProxmoxApi::class);
    $apiA->shouldReceive('node')->andReturn($nodeResourceA);

    $apiB = Mockery::mock(ProxmoxApi::class);
    $apiB->shouldReceive('node')->andReturn($nodeResourceB);

    $factory = Mockery::mock(ProxmoxApiFactory::class);
    $factory->shouldReceive('forCluster')->twice()->andReturn($apiA, $apiB);
    $this->app->instance(ProxmoxApiFactory::class, $factory);

    ProxmoxNode::factory()->create([
        'name' => 'pve-a',
        'hostname' => '192.168.1.10:8006',
        'is_active' => false,
    ]);

    ProxmoxNode::factory()->active()->create([
        'name' => 'pve-b',
        'hostname' => '192.168.1.11:8006',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('Proxmox クラスタ')
        ->assertSee('登録クラスタ数')
        ->assertSee('有効クラスタ数')
        ->assertSee('有効クラスタ CPU使用率 (平均)')
        ->assertSee('ノード別 CPU使用率')
        ->assertSee('40.0%')
        ->assertSee('pve-a')
        ->assertSee('pve-b')
        ->assertSee('pve1')
        ->assertSee('pve2')
        ->assertSee('20.0%')
        ->assertSee('40.0%')
        ->assertSee('192.168.1.10:8006')
        ->assertSee('192.168.1.11:8006');
});
