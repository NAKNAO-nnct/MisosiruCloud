<?php

declare(strict_types=1);

namespace App\Lib\Nomad\Resources;

use App\Lib\Nomad\Client;

class Quota
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param array<string, mixed> $spec
     */
    public function createQuota(array $spec): array
    {
        return $this->client->put('quota', $spec);
    }
}
