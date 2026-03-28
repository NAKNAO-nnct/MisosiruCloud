<?php

declare(strict_types=1);

namespace App\Services\CloudInit\Templates;

use App\Services\CloudInit\CloudInitBuilder;

abstract class BaseTemplate
{
    public function __construct(protected readonly CloudInitBuilder $builder)
    {
    }

    /**
     * @param array<string, mixed> $opts
     */
    abstract public function buildUserData(array $opts = []): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function proxmoxConfig(): array;
}
