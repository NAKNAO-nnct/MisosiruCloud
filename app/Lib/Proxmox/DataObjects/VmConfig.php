<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\DataObjects;

readonly class VmConfig
{
    public function __construct(
        public int $vmid,
        public string $name,
        public int $cores,
        public int $memory,
        public array $disks,
        public array $nets,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            vmid: (int) ($data['vmid'] ?? 0),
            name: $data['name'] ?? '',
            cores: (int) ($data['cores'] ?? 1),
            memory: (int) ($data['memory'] ?? 0),
            disks: array_filter(
                $data,
                fn ($key) => str_starts_with($key, 'scsi') || str_starts_with($key, 'virtio') || str_starts_with($key, 'ide'),
                ARRAY_FILTER_USE_KEY,
            ),
            nets: array_filter(
                $data,
                fn ($key) => str_starts_with($key, 'net'),
                ARRAY_FILTER_USE_KEY,
            ),
        );
    }
}
