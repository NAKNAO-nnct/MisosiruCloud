<?php

declare(strict_types=1);

namespace App\Services\CloudInit\Templates;

use App\Services\CloudInit\CloudInitBuilder;

class MysqlTemplate extends BaseTemplate
{
    public function __construct(
        CloudInitBuilder $builder,
        private readonly string $version = '8.0',
    ) {
        parent::__construct($builder);
    }

    public function buildUserData(array $opts = []): string
    {
        return $this->builder->buildUserData(array_merge([
            'packages' => ["mysql-server"],
            'runcmd' => [
                'systemctl enable mysql',
                'systemctl start mysql',
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
