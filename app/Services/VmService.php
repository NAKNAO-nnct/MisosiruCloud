<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenant\TenantData;
use App\Data\Vm\VmDetailResponseData;
use App\Data\Vm\VmMetaData;
use App\Enums\VmStatus;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Snippet\SnippetClientFactory;
use App\Repositories\VmMetaRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VmService
{
    public function __construct(
        private readonly ?ProxmoxApi $api,
        private readonly VmMetaRepository $vmMetaRepository,
        private readonly ?SnippetClientFactory $snippetClientFactory = null,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function listAllVms(): array
    {
        if (!$this->api) {
            return [];
        }

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

    public function getVmWithMeta(int $vmid): VmDetailResponseData
    {
        $meta = $this->vmMetaRepository->findByVmidWithTenant($vmid);

        if (!$this->api) {
            return VmDetailResponseData::make([
                'meta' => $meta,
                'status' => null,
                'node' => $meta?->getProxmoxNode(),
            ]);
        }

        $node = $meta?->getProxmoxNode();

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

        return VmDetailResponseData::make([
            'meta' => $meta,
            'status' => $vmStatus,
            'node' => $node,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function provisionVm(TenantData $tenant, array $params): VmMetaData
    {
        $this->ensureProxmoxApiConfigured();

        $vmMetaData = DB::transaction(fn () => $this->vmMetaRepository->create([
            'tenant_id' => $tenant->getId(),
            'proxmox_vmid' => $params['new_vmid'],
            'proxmox_node' => $params['node'],
            'purpose' => $params['purpose'] ?? null,
            'label' => $params['label'],
            'provisioning_status' => VmStatus::Pending,
        ]));

        try {
            $this->vmMetaRepository->update($vmMetaData->getId(), ['provisioning_status' => VmStatus::Cloning]);

            $upid = $this->api->vm()->cloneVm($params['node'], (int) $params['template_vmid'], [
                'newid' => $params['new_vmid'],
                'name' => $params['label'],
                'full' => 1,
            ]);

            $this->api->vm()->waitForTask($params['node'], $upid);

            $this->vmMetaRepository->update($vmMetaData->getId(), ['provisioning_status' => VmStatus::Configuring]);

            $snippetFilename = $this->buildSnippetFilename((int) $params['new_vmid']);
            $this->uploadVmSnippet($params['node'], $snippetFilename, $this->buildCloudInitUserData($tenant, $params));

            $config = array_filter([
                'cores' => $params['cpu'] ?? null,
                'memory' => $params['memory_mb'] ?? null,
                'cicustom' => sprintf(
                    'user=%s:snippets/%s',
                    (string) config('services.proxmox.snippet_storage', 'local'),
                    $snippetFilename,
                ),
            ]);

            if (!empty($config)) {
                $this->api->vm()->updateVmConfig($params['node'], (int) $params['new_vmid'], $config);
            }

            if (!empty($params['disk_gb'])) {
                $this->api->vm()->resizeVm($params['node'], (int) $params['new_vmid'], 'scsi0', "+{$params['disk_gb']}G");
            }

            $this->vmMetaRepository->update($vmMetaData->getId(), ['provisioning_status' => VmStatus::Starting]);

            $upid = $this->api->vm()->startVm($params['node'], (int) $params['new_vmid']);
            $this->api->vm()->waitForTask($params['node'], $upid);

            $this->vmMetaRepository->update($vmMetaData->getId(), ['provisioning_status' => VmStatus::Ready]);
        } catch (Throwable $e) {
            $this->vmMetaRepository->update($vmMetaData->getId(), [
                'provisioning_status' => VmStatus::Error,
                'provisioning_error' => $e->getMessage(),
            ]);

            throw new RuntimeException("VM provisioning failed: {$e->getMessage()}", 0, $e);
        }

        return $this->vmMetaRepository->findByIdOrFail($vmMetaData->getId());
    }

    public function terminateVm(VmMetaData $vmMeta): void
    {
        $this->ensureProxmoxApiConfigured();

        $this->deleteVmSnippet($vmMeta->getProxmoxNode(), $this->buildSnippetFilename($vmMeta->getProxmoxVmid()));

        try {
            $this->api->vm()->forceStopVm($vmMeta->getProxmoxNode(), $vmMeta->getProxmoxVmid());
        } catch (Throwable) {
            // VM may already be stopped
        }

        $this->api->vm()->deleteVm($vmMeta->getProxmoxNode(), $vmMeta->getProxmoxVmid());
        $this->vmMetaRepository->delete($vmMeta->getId());
    }

    public function startByVmid(int $vmid): void
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $this->api->vm()->startVm($vmMeta->getProxmoxNode(), $vmid);
    }

    public function stopByVmid(int $vmid): void
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $this->api->vm()->stopVm($vmMeta->getProxmoxNode(), $vmid);
    }

    public function forceStopByVmid(int $vmid): void
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $this->api->vm()->forceStopVm($vmMeta->getProxmoxNode(), $vmid);
    }

    public function rebootByVmid(int $vmid): void
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $this->api->vm()->rebootVm($vmMeta->getProxmoxNode(), $vmid);
    }

    public function resizeByVmid(int $vmid, string $disk, string $size): void
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $this->api->vm()->resizeVm($vmMeta->getProxmoxNode(), $vmid, $disk, $size);
    }

    public function createSnapshotByVmid(int $vmid, string $name): void
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $this->api->vm()->createSnapshot($vmMeta->getProxmoxNode(), $vmid, $name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getVncProxyByVmid(int $vmid): array
    {
        $this->ensureProxmoxApiConfigured();

        $vmMeta = $this->vmMetaRepository->findByVmidOrFail($vmid);

        return $this->api->vm()->getVncProxy($vmMeta->getProxmoxNode(), $vmid);
    }

    /**
     * Returns nodes and the next available VMID from Proxmox for use in provision forms.
     *
     * @return array{nodes: array<int, string>, nextVmid: int|null}
     */
    public function getFormOptions(): array
    {
        if (!$this->api) {
            return ['nodes' => [], 'nextVmid' => null];
        }

        $nodes = array_column($this->api->node()->listNodes(), 'node');
        $nextVmid = $this->api->cluster()->nextId();

        return ['nodes' => $nodes, 'nextVmid' => $nextVmid];
    }

    private function ensureProxmoxApiConfigured(): void
    {
        if (!$this->api) {
            throw new RuntimeException('No active Proxmox node configured.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildCloudInitUserData(TenantData $tenant, array $params): string
    {
        $hostname = (string) ($params['label'] ?? ('vm-' . $params['new_vmid']));

        return implode("\n", [
            '#cloud-config',
            'hostname: ' . $hostname,
            'fqdn: ' . $hostname . '.' . $tenant->getSlug() . '.local',
            'manage_etc_hosts: true',
            '',
        ]);
    }

    private function buildSnippetFilename(int $vmid): string
    {
        return sprintf('vm-%d-user-data.yaml', $vmid);
    }

    private function uploadVmSnippet(string $nodeName, string $filename, string $content): void
    {
        if (!$this->snippetClientFactory) {
            return;
        }

        $client = $this->snippetClientFactory->forNodeIfConfigured($nodeName);

        if (!$client) {
            return;
        }

        $client->upload($filename, $content);
    }

    private function deleteVmSnippet(string $nodeName, string $filename): void
    {
        if (!$this->snippetClientFactory) {
            return;
        }

        try {
            $client = $this->snippetClientFactory->forNodeIfConfigured($nodeName);

            if (!$client) {
                return;
            }

            $client->delete($filename);
        } catch (Throwable) {
            // Snippet cleanup failure should not block VM termination.
        }
    }
}
