<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\Resources;

use App\Lib\Proxmox\Client;
use App\Lib\Proxmox\DataObjects\NodeStatus;

class Node
{
    public function __construct(private readonly Client $client) {}

    public function listNodes(): array
    {
        return $this->client->get('nodes');
    }

    public function getNodeStatus(string $node): NodeStatus
    {
        $data = $this->client->get("nodes/{$node}/status");

        return NodeStatus::from(array_merge($data, ['node' => $node]));
    }
}
