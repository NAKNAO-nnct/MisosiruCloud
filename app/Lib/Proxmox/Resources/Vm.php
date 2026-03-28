<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\Resources;

use App\Lib\Proxmox\Client;
use App\Lib\Proxmox\DataObjects\VmConfig;
use App\Lib\Proxmox\DataObjects\VmStatus;

class Vm
{
    public function __construct(private readonly Client $client)
    {
    }

    public function listVms(string $node): array
    {
        return $this->client->get("nodes/{$node}/qemu");
    }

    public function getVmConfig(string $node, int $vmid): VmConfig
    {
        $data = $this->client->get("nodes/{$node}/qemu/{$vmid}/config");

        return VmConfig::from(array_merge($data, ['vmid' => $vmid]));
    }

    public function updateVmConfig(string $node, int $vmid, array $params): array
    {
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/config", $params);
    }

    public function createVm(string $node, array $params): array
    {
        return $this->client->post("nodes/{$node}/qemu", $params);
    }

    public function deleteVm(string $node, int $vmid): array
    {
        return $this->client->delete("nodes/{$node}/qemu/{$vmid}");
    }

    public function getVmStatus(string $node, int $vmid): VmStatus
    {
        $data = $this->client->get("nodes/{$node}/qemu/{$vmid}/status/current");

        return VmStatus::from($data);
    }

    public function startVm(string $node, int $vmid): string
    {
        $data = $this->client->post("nodes/{$node}/qemu/{$vmid}/status/start");

        return $data['upid'] ?? '';
    }

    public function stopVm(string $node, int $vmid): string
    {
        $data = $this->client->post("nodes/{$node}/qemu/{$vmid}/status/stop");

        return $data['upid'] ?? '';
    }

    public function rebootVm(string $node, int $vmid): string
    {
        $data = $this->client->post("nodes/{$node}/qemu/{$vmid}/status/reboot");

        return $data['upid'] ?? '';
    }

    public function forceStopVm(string $node, int $vmid): string
    {
        $data = $this->client->post("nodes/{$node}/qemu/{$vmid}/status/stop", ['forceStop' => 1]);

        return $data['upid'] ?? '';
    }

    public function cloneVm(string $node, int $vmid, array $params): string
    {
        $data = $this->client->post("nodes/{$node}/qemu/{$vmid}/clone", $params);

        return $data['upid'] ?? '';
    }

    public function resizeVm(string $node, int $vmid, string $disk, string $size): array
    {
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/resize", [
            'disk' => $disk,
            'size' => $size,
        ]);
    }

    public function listSnapshots(string $node, int $vmid): array
    {
        return $this->client->get("nodes/{$node}/qemu/{$vmid}/snapshot");
    }

    public function createSnapshot(string $node, int $vmid, string $name): string
    {
        $data = $this->client->post("nodes/{$node}/qemu/{$vmid}/snapshot", ['snapname' => $name]);

        return $data['upid'] ?? '';
    }

    public function getVncProxy(string $node, int $vmid): array
    {
        return $this->client->post("nodes/{$node}/qemu/{$vmid}/vncproxy");
    }

    public function regenerateCloudinit(string $node, int $vmid): array
    {
        return $this->client->put("nodes/{$node}/qemu/{$vmid}/cloudinit");
    }

    public function waitForTask(string $node, string $upid, int $timeout = 60): bool
    {
        $start = time();
        $encodedUpid = urlencode($upid);

        while (time() - $start < $timeout) {
            $data = $this->client->get("nodes/{$node}/tasks/{$encodedUpid}/status");

            if (($data['status'] ?? '') === 'stopped') {
                return ($data['exitstatus'] ?? '') === 'OK';
            }

            sleep(2);
        }

        return false;
    }
}
