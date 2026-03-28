<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\Resources;

use App\Lib\Proxmox\Client;

class Storage
{
    public function __construct(private readonly Client $client)
    {
    }

    public function listStorage(string $node): array
    {
        return $this->client->get("nodes/{$node}/storage");
    }

    public function listStorageContent(string $node, string $storage): array
    {
        return $this->client->get("nodes/{$node}/storage/{$storage}/content");
    }
}
