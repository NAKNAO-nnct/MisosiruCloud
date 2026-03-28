<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VmStatus;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\Tenant;
use App\Models\VmMeta;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VmService
{
    public function __construct(private readonly ProxmoxApi $api)
    {
    }

    /**
     * @return array<int, mixed>
     */
    public function listAllVms(): array
    {
        $nodes = $this->api->node()->listNodes();
        $vms = [];

        foreach ($nodes as $node) {
            $nodeName = $node['node'];
            $nodeVms = $this->api->vm()->listVms($nodeName);
            foreach ($nodeVms as $vm) {
                $vms[] = array_merge($vm, ['node' => $nodeName]);
            }
        }

        return $vms;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVmWithMeta(int $vmid): array
    {
        $meta = VmMeta::query()
            ->where('proxmox_vmid', $vmid)
            ->with('tenant')
            ->first();

        $node = $meta?->proxmox_node;

        $nodes = $node ? [$node] : array_column($this->api->node()->listNodes(), 'node');
        $vmStatus = null;

        foreach ($nodes as $n) {
            try {
                $vmStatus = $this->api->vm()->getVmStatus($n, $vmid);
                $node = $n;

                break;
            } catch (Throwable) {
                continue;
            }
        }

        return [
            'meta' => $meta,
            'status' => $vmStatus,
            'node' => $node,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function provisionVm(Tenant $tenant, array $params): VmMeta
    {
        $vmMeta = DB::transaction(fn () => VmMeta::create([
            'tenant_id' => $tenant->id,
            'proxmox_vmid' => $params['new_vmid'],
            'proxmox_node' => $params['node'],
            'purpose' => $params['purpose'] ?? null,
            'label' => $params['label'],
            'provisioning_status' => VmStatus::Pending,
        ]));

        try {
            $vmMeta->update(['provisioning_status' => VmStatus::Cloning]);

            $upid = $this->api->vm()->cloneVm($params['node'], (int) $params['template_vmid'], [
                'newid' => $params['new_vmid'],
                'name' => $params['label'],
                'full' => 1,
            ]);

            $this->api->vm()->waitForTask($params['node'], $upid);

            $vmMeta->update(['provisioning_status' => VmStatus::Configuring]);

            $config = array_filter([
                'cores' => $params['cpu'] ?? null,
                'memory' => $params['memory_mb'] ?? null,
            ]);

            if (!empty($config)) {
                $this->api->vm()->updateVmConfig($params['node'], (int) $params['new_vmid'], $config);
            }

            if (!empty($params['disk_gb'])) {
                $this->api->vm()->resizeVm($params['node'], (int) $params['new_vmid'], 'scsi0', "+{$params['disk_gb']}G");
            }

            $vmMeta->update(['provisioning_status' => VmStatus::Starting]);

            $upid = $this->api->vm()->startVm($params['node'], (int) $params['new_vmid']);
            $this->api->vm()->waitForTask($params['node'], $upid);

            $vmMeta->update(['provisioning_status' => VmStatus::Ready]);
        } catch (Throwable $e) {
            $vmMeta->update([
                'provisioning_status' => VmStatus::Error,
                'provisioning_error' => $e->getMessage(),
            ]);

            throw new RuntimeException("VM provisioning failed: {$e->getMessage()}", 0, $e);
        }

        return $vmMeta->refresh();
    }

    public function terminateVm(VmMeta $vmMeta): void
    {
        try {
            $this->api->vm()->forceStopVm($vmMeta->proxmox_node, (int) $vmMeta->proxmox_vmid);
        } catch (Throwable) {
            // VM may already be stopped
        }

        $this->api->vm()->deleteVm($vmMeta->proxmox_node, (int) $vmMeta->proxmox_vmid);
        $vmMeta->delete();
    }
}

