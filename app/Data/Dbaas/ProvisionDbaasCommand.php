<?php

declare(strict_types=1);

namespace App\Data\Dbaas;

use App\Enums\DatabaseType;

final readonly class ProvisionDbaasCommand
{
    private function __construct(
        private int $tenantId,
        private DatabaseType $dbType,
        private string $dbVersion,
        private ?string $label,
        private int $templateVmid,
        private string $node,
        private int $newVmid,
        private int $cpu,
        private int $memoryMb,
        private ?int $diskGb,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $dbType = $attributes['db_type'] ?? DatabaseType::Mysql;

        return new self(
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            dbType: $dbType instanceof DatabaseType ? $dbType : DatabaseType::from((string) $dbType),
            dbVersion: (string) ($attributes['db_version'] ?? ''),
            label: isset($attributes['label']) ? (string) $attributes['label'] : null,
            templateVmid: (int) ($attributes['template_vmid'] ?? 0),
            node: (string) ($attributes['node'] ?? ''),
            newVmid: (int) ($attributes['new_vmid'] ?? 0),
            cpu: (int) ($attributes['cpu'] ?? 1),
            memoryMb: (int) ($attributes['memory_mb'] ?? 512),
            diskGb: isset($attributes['disk_gb']) ? (int) $attributes['disk_gb'] : null,
        );
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getDbType(): DatabaseType
    {
        return $this->dbType;
    }

    public function getDbVersion(): string
    {
        return $this->dbVersion;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getTemplateVmid(): int
    {
        return $this->templateVmid;
    }

    public function getNode(): string
    {
        return $this->node;
    }

    public function getNewVmid(): int
    {
        return $this->newVmid;
    }

    public function getCpu(): int
    {
        return $this->cpu;
    }

    public function getMemoryMb(): int
    {
        return $this->memoryMb;
    }

    public function getDiskGb(): ?int
    {
        return $this->diskGb;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'db_type' => $this->dbType,
            'db_version' => $this->dbVersion,
            'label' => $this->label,
            'template_vmid' => $this->templateVmid,
            'node' => $this->node,
            'new_vmid' => $this->newVmid,
            'cpu' => $this->cpu,
            'memory_mb' => $this->memoryMb,
            'disk_gb' => $this->diskGb,
        ];
    }
}
