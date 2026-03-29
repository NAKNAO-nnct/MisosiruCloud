<?php

declare(strict_types=1);

namespace App\Lib\Nomad\Resources;

use App\Lib\Nomad\Client;

class Node
{
    public function __construct(private readonly Client $client)
    {
    }

    public function listNodes(): array
    {
        return $this->client->get('nodes');
    }
}
