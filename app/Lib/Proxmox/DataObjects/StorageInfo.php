<?php

declare(strict_types=1);

namespace App\Lib\Proxmox\DataObjects;

readonly class StorageInfo
{
    public function __construct(
        public string $storage,
        public string $type,
        public int $avail,
        public int $total,
        public int $used,
    ) {
    }

    public static function from(array $data): self
    {
        return new self(
            storage: $data['storage'] ?? '',
            type: $data['type'] ?? '',
            avail: (int) ($data['avail'] ?? 0),
            total: (int) ($data['total'] ?? 0),
            used: (int) ($data['used'] ?? 0),
        );
    }
}
