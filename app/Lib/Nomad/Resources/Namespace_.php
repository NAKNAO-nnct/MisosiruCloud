<?php

declare(strict_types=1);

namespace App\Lib\Nomad\Resources;

use App\Lib\Nomad\Client;

class Namespace_
{
    public function __construct(private readonly Client $client)
    {
    }

    public function listNamespaces(): array
    {
        return $this->client->get('namespaces');
    }

    public function createNamespace(string $name, string $description = ''): array
    {
        return $this->client->post('namespace', [
            'Name' => $name,
            'Description' => $description,
        ]);
    }

    public function deleteNamespace(string $name): array
    {
        return $this->client->delete("namespace/{$name}");
    }
}
