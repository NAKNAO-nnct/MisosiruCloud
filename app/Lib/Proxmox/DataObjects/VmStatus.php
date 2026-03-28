<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\DataObjects;

readonly class VmStatus
{
    public function __construct(
        public string $status,
        public int $cpus,
        public int $maxcpu,
        public int $mem,
        public int $maxmem,
        public int $uptime,
        public ?int $pid,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            status: $data['status'] ?? 'unknown',
            cpus: (int) ($data['cpus'] ?? 0),
            maxcpu: (int) ($data['maxcpu'] ?? 0),
            mem: (int) ($data['mem'] ?? 0),
            maxmem: (int) ($data['maxmem'] ?? 0),
            uptime: (int) ($data['uptime'] ?? 0),
            pid: isset($data['pid']) ? (int) $data['pid'] : null,
        );
    }
}
