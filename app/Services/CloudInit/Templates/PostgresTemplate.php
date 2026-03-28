<?php

declare(strict_types=1);

namespace App\Services\CloudInit\Templates;

use App\Services\CloudInit\CloudInitBuilder;

class PostgresTemplate extends BaseTemplate
{
    public function __construct(
        CloudInitBuilder $builder,
        private readonly string $version = '16',
    ) {
        parent::__construct($builder);
    }

    public function buildUserData(array $opts = []): string
    {
        return $this->builder->buildUserData(array_merge([
            'packages' => ["postgresql-{$this->version}"],
            'runcmd' => [
                'systemctl enable postgresql',
                'systemctl start postgresql',
            ],
        ], $opts));
    }

    public function proxmoxConfig(): array
    {
        return [
            'cores' => 2,
            'memory' => 4096,
        ];
    }
}
