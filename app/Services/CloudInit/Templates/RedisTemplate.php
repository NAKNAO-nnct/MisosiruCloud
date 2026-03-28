<?php

declare(strict_types=1);

namespace App\Services\CloudInit\Templates;

use App\Services\CloudInit\CloudInitBuilder;

class RedisTemplate extends BaseTemplate
{
    public function __construct(CloudInitBuilder $builder)
    {
        parent::__construct($builder);
    }

    public function buildUserData(array $opts = []): string
    {
        return $this->builder->buildUserData(array_merge([
            'packages' => ['redis-server'],
            'runcmd' => [
                'systemctl enable redis-server',
                'systemctl start redis-server',
            ],
        ], $opts));
    }

    public function proxmoxConfig(): array
    {
        return [
            'cores' => 1,
            'memory' => 1024,
        ];
    }
}
