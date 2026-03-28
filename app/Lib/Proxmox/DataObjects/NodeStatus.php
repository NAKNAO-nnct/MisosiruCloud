<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\DataObjects;

readonly class NodeStatus
{
    public function __construct(
        public string $node,
        public string $status,
        public float $cpu,
        public array $cpuinfo,
        public int $mem,
        public int $maxmem,
        public int $disk,
        public int $maxdisk,
    ) {
    }

    public static function from(array $data): self
    {
        return new self(
            node: $data['node'] ?? '',
            status: $data['status'] ?? 'unknown',
            cpu: (float) ($data['cpu'] ?? 0.0),
            cpuinfo: $data['cpuinfo'] ?? [],
            mem: (int) ($data['mem'] ?? 0),
            maxmem: (int) ($data['maxmem'] ?? 0),
            disk: (int) ($data['disk'] ?? 0),
            maxdisk: (int) ($data['maxdisk'] ?? 0),
        );
    }
}
