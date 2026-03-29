<?php

declare(strict_types=1);

namespace App\Lib\Nomad\Resources;

use App\Lib\Nomad\Client;

class Allocation
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getAllocation(string $allocId): array
    {
        return $this->client->get("allocation/{$allocId}");
    }

    public function getAllocationLogs(string $allocId, string $taskName, string $logType = 'stdout'): string
    {
        return $this->client->getRaw("client/fs/logs/{$allocId}", [
            'task' => $taskName,
            'type' => $logType,
            'plain' => true,
        ]);
    }
}
