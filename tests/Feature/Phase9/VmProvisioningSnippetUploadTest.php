<?php

declare(strict_types=1);

use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Proxmox\Resources\Vm;
use App\Models\ProxmoxNode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VmMeta;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('vm provisioning flow uploads snippet file', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-sidecar']);

    ProxmoxNode::factory()->active()->create([
        'name' => 'pve1',
        'snippet_api_url' => 'http://snippet.local',
        'snippet_api_token_encrypted' => 'snippet-token',
    ]);

    $vmResource = Mockery::mock(Vm::class);
    $vmResource->shouldReceive('cloneVm')->once()->andReturn('UPID:pve1:clone');
    $vmResource->shouldReceive('waitForTask')->twice()->andReturn(true);
    $vmResource->shouldReceive('updateVmConfig')->once()->andReturn([]);
    $vmResource->shouldReceive('startVm')->once()->andReturn('UPID:pve1:start');

    $api = Mockery::mock(ProxmoxApi::class);
    $api->shouldReceive('vm')->andReturn($vmResource);

    app()->instance(ProxmoxApi::class, $api);

    Http::fake([
        'http://snippet.local/snippets' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('vms.store'), [
            'tenant_id' => $tenant->id,
            'label' => 'app-vm',
            'template_vmid' => 9000,
            'node' => 'pve1',
            'new_vmid' => 310,
            'cpu' => 2,
            'memory_mb' => 2048,
        ])
        ->assertRedirect(route('vms.index'));

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'http://snippet.local/snippets'
        && str_contains($request->body(), 'vm-310-user-data.yaml'));

    expect(VmMeta::query()->where('proxmox_vmid', 310)->exists())->toBeTrue();
});
