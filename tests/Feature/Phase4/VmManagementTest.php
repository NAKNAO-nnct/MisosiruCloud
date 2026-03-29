<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\VmStatus;
use App\Jobs\DestroyVmJob;
use App\Jobs\ProvisionVmJob;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\Resources\Node;
use App\Lib\Proxmox\Resources\Vm;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VmMeta;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('非ログインユーザはVM一覧にアクセスできない', function (): void {
    $this->get(route('vms.index'))->assertRedirect('/login');
});

test('一般ユーザはVM一覧にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('vms.index'))
        ->assertForbidden();
});

test('管理者はVM一覧にアクセスでき、Proxmox APIのレスポンスが反映される', function (): void {
    $vmResource = Mockery::mock(Vm::class);
    $nodeResource = Mockery::mock(Node::class);

    $nodeResource->shouldReceive('listNodes')
        ->once()
        ->andReturn([['node' => 'pve1'], ['node' => 'pve2']]);

    $vmResource->shouldReceive('listVms')
        ->with('pve1')
        ->once()
        ->andReturn([
            ['vmid' => 100, 'name' => 'vm-mysql', 'status' => 'running', 'node' => 'pve1'],
        ]);

    $vmResource->shouldReceive('listVms')
        ->with('pve2')
        ->once()
        ->andReturn([
            ['vmid' => 200, 'name' => 'vm-redis', 'status' => 'stopped', 'node' => 'pve2'],
        ]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('node')->andReturn($nodeResource);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $this->app->instance(ProxmoxApi::class, $api);

    $this->actingAs($this->admin)
        ->get(route('vms.index'))
        ->assertSuccessful()
        ->assertSee('vm-mysql')
        ->assertSee('vm-redis');
});

test('複数ノードのVMが正しく集約される', function (): void {
    $vmResource = Mockery::mock(Vm::class);
    $nodeResource = Mockery::mock(Node::class);

    $nodeResource->shouldReceive('listNodes')
        ->andReturn([['node' => 'pve1'], ['node' => 'pve2'], ['node' => 'pve3']]);

    $vmResource->shouldReceive('listVms')->with('pve1')->andReturn([['vmid' => 101, 'name' => 'vm-a', 'status' => 'running']]);
    $vmResource->shouldReceive('listVms')->with('pve2')->andReturn([['vmid' => 102, 'name' => 'vm-b', 'status' => 'stopped']]);
    $vmResource->shouldReceive('listVms')->with('pve3')->andReturn([]);

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('node')->andReturn($nodeResource);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $this->app->instance(ProxmoxApi::class, $api);

    $this->actingAs($this->admin)
        ->get(route('vms.index'))
        ->assertSuccessful()
        ->assertSee('vm-a')
        ->assertSee('vm-b');
});

test('VM起動APIコールが呼ばれる', function (): void {
    $tenant = Tenant::factory()->create();
    $vmMeta = VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 100,
        'proxmox_node' => 'pve1',
        'provisioning_status' => VmStatus::Ready,
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('startVm')
        ->with('pve1', 100)
        ->once()
        ->andReturn('UPID:pve1:start');

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $this->app->instance(ProxmoxApi::class, $api);

    $this->actingAs($this->admin)
        ->post(route('vms.start', 100))
        ->assertRedirect(route('vms.show', 100));
});

test('VM停止APIコールが呼ばれる', function (): void {
    $tenant = Tenant::factory()->create();
    VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 100,
        'proxmox_node' => 'pve1',
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('stopVm')
        ->with('pve1', 100)
        ->once()
        ->andReturn('UPID:pve1:stop');

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);

    $this->app->instance(ProxmoxApi::class, $api);

    $this->actingAs($this->admin)
        ->post(route('vms.stop', 100))
        ->assertRedirect(route('vms.show', 100));
});

test('別テナントのVMMetaに他テナントメンバーがアクセスできないこと', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userB = User::factory()->create(['role' => UserRole::TenantMember]);
    $tenantB->users()->attach($userB);

    VmMeta::factory()->create([
        'tenant_id' => $tenantA->id,
        'proxmox_vmid' => 100,
        'proxmox_node' => 'pve1',
    ]);

    // 一般ユーザなので管理者ルートへのアクセスは forbidden
    $this->actingAs($userB)
        ->get(route('vms.index'))
        ->assertForbidden();
});

test('VM作成時にProvisionVmJobがキュー投入される', function (): void {
    $tenant = Tenant::factory()->create();
    Queue::fake();

    $this->actingAs($this->admin)
        ->post(route('vms.store'), [
            'tenant_id' => $tenant->id,
            'label' => 'test-vm',
            'template_vmid' => 9000,
            'node' => 'pve1',
            'new_vmid' => 200,
            'cpu' => 2,
            'memory_mb' => 2048,
            'ip_address' => '10.1.0.10',
            'gateway' => '10.1.0.1',
            'vnet_name' => 'vnet_1',
        ])
        ->assertRedirect(route('vms.show', 200));

    Queue::assertPushed(ProvisionVmJob::class);
});

test('VM削除時にDestroyVmJobがキュー投入される', function (): void {
    $tenant = Tenant::factory()->create();
    $vmMeta = VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 330,
        'proxmox_node' => 'pve1',
    ]);

    Queue::fake();

    $this->actingAs($this->admin)
        ->delete(route('vms.destroy', $vmMeta->proxmox_vmid))
        ->assertRedirect(route('vms.index'));

    Queue::assertPushed(DestroyVmJob::class);
});

test('VM詳細はプロビジョニング中に自動更新メッセージを表示する', function (): void {
    $tenant = Tenant::factory()->create();
    $vmMeta = VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 410,
        'proxmox_node' => 'pve1',
        'label' => 'pending-vm',
        'provisioning_status' => VmStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->get(route('vms.show', $vmMeta->proxmox_vmid))
        ->assertSuccessful()
        ->assertSee('プロビジョニング中です。5秒ごとに自動更新しています。')
        ->assertSee('setTimeout', false);
});

test('VM詳細はエラー時に再試行ボタンを表示し、作成画面へ既知値を引き継ぐ', function (): void {
    $tenant = Tenant::factory()->create();
    $vmMeta = VmMeta::factory()->create([
        'tenant_id' => $tenant->id,
        'proxmox_vmid' => 411,
        'proxmox_node' => 'pve1',
        'label' => 'error-vm',
        'purpose' => 'app',
        'provisioning_status' => VmStatus::Error,
        'provisioning_error' => 'clone failed',
    ]);

    $this->actingAs($this->admin)
        ->get(route('vms.show', $vmMeta->proxmox_vmid))
        ->assertSuccessful()
        ->assertSee('再試行')
        ->assertSee('clone failed')
        ->assertSee(route('vms.create', [
            'tenant_id' => $tenant->id,
            'label' => 'error-vm',
            'node' => 'pve1',
            'new_vmid' => 411,
            'purpose' => 'app',
        ]));
});

