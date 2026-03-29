<?php

declare(strict_types=1);

namespace App\Data\Vm;

final readonly class ProvisionVmCommand
{
    private function __construct(
        private int $tenantId,
        private string $label,
        private int $templateVmid,
        private string $node,
        private int $newVmid,
        private ?int $cpu,
        private ?int $memoryMb,
        private ?int $diskGb,
        private ?string $purpose,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        return new self(
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            label: (string) ($attributes['label'] ?? ''),
            templateVmid: (int) ($attributes['template_vmid'] ?? 0),
            node: (string) ($attributes['node'] ?? ''),
            newVmid: (int) ($attributes['new_vmid'] ?? 0),
            cpu: isset($attributes['cpu']) ? (int) $attributes['cpu'] : null,
            memoryMb: isset($attributes['memory_mb']) ? (int) $attributes['memory_mb'] : null,
            diskGb: isset($attributes['disk_gb']) ? (int) $attributes['disk_gb'] : null,
            purpose: isset($attributes['purpose']) ? (string) $attributes['purpose'] : null,
        );
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getLabel(): string
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

    public function getCpu(): ?int
    {
        return $this->cpu;
    }

    public function getMemoryMb(): ?int
    {
        return $this->memoryMb;
    }

    public function getDiskGb(): ?int
    {
        return $this->diskGb;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'label' => $this->label,
            'template_vmid' => $this->templateVmid,
            'node' => $this->node,
            'new_vmid' => $this->newVmid,
            'cpu' => $this->cpu,
            'memory_mb' => $this->memoryMb,
            'disk_gb' => $this->diskGb,
            'purpose' => $this->purpose,
        ];
    }
}
