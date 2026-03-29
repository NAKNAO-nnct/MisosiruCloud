<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenant\TenantData;
use App\Data\Vm\ProvisionVmCommand;
use App\Data\Vm\VmDetailResponseData;
use App\Data\Vm\VmMetaData;
use App\Enums\VmStatus;
use App\Lib\Proxmox\ProxmoxApi;
use App\Lib\Snippet\SnippetClientFactory;
use App\Repositories\VmMetaRepository;
use App\Services\CloudInit\CloudInitBuilder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VmService
{
    public function __construct(
        private readonly ?ProxmoxApi $api,
        private readonly VmMetaRepository $vmMetaRepository,
        private readonly ?SnippetClientFactory $snippetClientFactory = null,
        private readonly ?CloudInitBuilder $cloudInitBuilder = null,
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
     * vm_metas レコードを同期的に作成して返す。
     * Controller から呼び出し、返された ID を ProvisionVmJob に渡す。
     */
    public function createVmMeta(TenantData $tenant, ProvisionVmCommand $command): VmMetaData
    {
        return DB::transaction(fn () => $this->vmMetaRepository->create([
            'tenant_id' => $tenant->getId(),
            'proxmox_vmid' => $command->getNewVmid(),
            'proxmox_node' => $command->getNode(),
            'purpose' => $command->getPurpose(),
            'label' => $command->getLabel(),
            'ip_address' => $command->getIpAddress(),
            'gateway' => $command->getGateway(),
            'vnet_name' => $command->getVnetName(),
            'shared_ip_address' => $command->getSharedIpAddress(),
            'ssh_keys' => $command->getSshKeys(),
            'provisioning_status' => VmStatus::Pending,
        ]));
    }

    /**
     * ProvisionVmJob から呼び出される非同期プロビジョニング処理（テンプレート VMID 付き）。
     *
     * @param array<string, mixed> $templateParams ['template_vmid' => int, 'disk_gb' => int|null]
     */
    public function provisionVm(VmMetaData $vmMeta, array $templateParams): void
    {
        $this->ensureProxmoxApiConfigured();

        $builder = $this->cloudInitBuilder ?? new CloudInitBuilder();
        $vmid = $vmMeta->getProxmoxVmid();
        $node = $vmMeta->getProxmoxNode();
        $hostname = $vmMeta->getLabel();
        $fqdn = $hostname . '.local';
        $ipAddress = $vmMeta->getIpAddress() ?? '';
        $gateway = $vmMeta->getGateway() ?? '';
        $vnetName = $vmMeta->getVnetName() ?? '';
        $sharedIp = $vmMeta->getSharedIpAddress();
        $sshKeys = $vmMeta->getSshKeys();
        $templateVmid = (int) ($templateParams['template_vmid'] ?? 0);
        $diskGb = isset($templateParams['disk_gb']) ? (int) $templateParams['disk_gb'] : null;
        $cpu = isset($templateParams['cpu']) ? (int) $templateParams['cpu'] : 2;
        $memoryMb = isset($templateParams['memory_mb']) ? (int) $templateParams['memory_mb'] : 2048;

        try {
            $this->vmMetaRepository->update($vmMeta->getId(), ['provisioning_status' => VmStatus::Uploading]);

            $userData = $builder->buildUserData([
                'hostname' => $hostname,
                'fqdn' => $fqdn,
                'ssh_keys' => $sshKeys,
            ]);
            $networkConfig = $builder->buildNetworkConfig(
                ipCidr: $ipAddress . '/24',
                gateway: $gateway,
                sharedIp: $sharedIp,
            );
            $metaData = $builder->buildMetaData($vmid, $hostname);

            $this->uploadVmSnippet($node, $vmid, $userData, $networkConfig, $metaData);

            $this->vmMetaRepository->update($vmMeta->getId(), ['provisioning_status' => VmStatus::Cloning]);

            $upid = $this->api->vm()->cloneVm($node, $templateVmid, [
                'newid' => $vmid,
                'name' => $hostname,
                'full' => 1,
            ]);
            $this->api->vm()->waitForTask($node, $upid);

            $this->vmMetaRepository->update($vmMeta->getId(), ['provisioning_status' => VmStatus::Configuring]);

            $storage = (string) config('services.proxmox.snippet_storage', 'local');
            $config = [
                'cores' => $cpu,
                'memory' => $memoryMb,
                'net0' => 'virtio,bridge=' . $vnetName,
                'cicustom' => sprintf(
                    'user=%1$s:snippets/vm-%2$d-user-data.yaml,network=%1$s:snippets/vm-%2$d-network-config.yaml,meta=%1$s:snippets/vm-%2$d-meta-data.yaml',
                    $storage,
                    $vmid,
                ),
            ];

            if ($sharedIp !== null && $sharedIp !== '') {
                $config['net1'] = 'virtio,bridge=vmbr1';
            }

            $this->api->vm()->updateVmConfig($node, $vmid, $config);

            if ($diskGb !== null && $diskGb > 0) {
                $this->api->vm()->resizeVm($node, $vmid, 'scsi0', "+{$diskGb}G");
            }

            $this->vmMetaRepository->update($vmMeta->getId(), ['provisioning_status' => VmStatus::Starting]);

            $upid = $this->api->vm()->startVm($node, $vmid);
            $this->api->vm()->waitForTask($node, $upid);

            $this->vmMetaRepository->update($vmMeta->getId(), ['provisioning_status' => VmStatus::Ready]);
        } catch (Throwable $e) {
            $this->vmMetaRepository->update($vmMeta->getId(), [
                'provisioning_status' => VmStatus::Error,
                'provisioning_error' => $e->getMessage(),
            ]);

            throw new RuntimeException("VM provisioning failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function terminateVm(VmMetaData $vmMeta): void
    {
        $this->ensureProxmoxApiConfigured();

        $this->deleteVmSnippet($vmMeta->getProxmoxNode(), $vmMeta->getProxmoxVmid());

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
    /**
     * 指定した VMID が Proxmox クラスタ上に存在するかチェックする。
     * 存在する場合はそのノード名を返し、存在しない場合は null を返す。
     */
    public function vmidExistsOnCluster(int $vmid): ?string
    {
        if (!$this->api) {
            return null;
        }

        foreach ($this->listAllVms() as $vm) {
            if ((int) ($vm['vmid'] ?? 0) === $vmid) {
                return (string) ($vm['node'] ?? '');
            }
        }

        return null;
    }

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

    private function uploadVmSnippet(
        string $nodeName,
        int $vmId,
        string $userData,
        ?string $networkConfig = null,
        ?string $metaData = null,
    ): void {
        if (!$this->snippetClientFactory) {
            return;
        }

        $client = $this->snippetClientFactory->forNodeIfConfigured($nodeName);

        if (!$client) {
            return;
        }

        $client->upload($vmId, $userData, $networkConfig, $metaData);
    }

    private function deleteVmSnippet(string $nodeName, int $vmId): void
    {
        if (!$this->snippetClientFactory) {
            return;
        }

        try {
            $client = $this->snippetClientFactory->forNodeIfConfigured($nodeName);

            if (!$client) {
                return;
            }

            $client->delete($vmId);
        } catch (Throwable) {
            // Snippet cleanup failure should not block VM termination.
        }
    }
}
