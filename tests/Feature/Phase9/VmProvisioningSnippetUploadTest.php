<?php

declare(strict_types=1);

use App\Jobs\ProvisionVmJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('vm provisioning request dispatches ProvisionVmJob', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-sidecar']);
    Queue::fake();

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

    Queue::assertPushed(ProvisionVmJob::class);
});
