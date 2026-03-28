<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\Resources;

use App\Lib\Proxmox\Client;

class Network
{
    public function __construct(private readonly Client $client)
    {
    }

    public function listNetworks(string $node): array
    {
        return $this->client->get("nodes/{$node}/network");
    }
}
