<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\Resources;

use App\Lib\Proxmox\Client;

class Cluster
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getClusterStatus(): array
    {
        return $this->client->get('cluster/status');
    }

    public function getResources(string $type = ''): array
    {
        $params = $type !== '' ? ['type' => $type] : [];

        return $this->client->get('cluster/resources', $params);
    }

    public function listVnets(): array
    {
        return $this->client->get('cluster/sdn/vnets');
    }

    public function createVnet(array $params): array
    {
        return $this->client->post('cluster/sdn/vnets', $params);
    }

    public function deleteVnet(string $vnet): array
    {
        return $this->client->delete("cluster/sdn/vnets/{$vnet}");
    }

    public function createSubnet(string $vnet, array $params): array
    {
        return $this->client->post("cluster/sdn/vnets/{$vnet}/subnets", $params);
    }

    public function listZones(): array
    {
        return $this->client->get('cluster/sdn/zones');
    }

    public function applySdn(): array
    {
        return $this->client->put('cluster/sdn');
    }

    public function nextId(): int
    {
        $data = $this->client->get('cluster/nextid');

        return (int) ($data['data'] ?? 0);
    }
}
