<?php

declare(strict_types=1);

namespace App\Data\Container;

final readonly class DeployContainerCommand
{
    /**
     * @param array<int, array<string, mixed>>|null $portMappings
     * @param array<string, string>|null            $envVars
     */
    private function __construct(
        private int $tenantId,
        private string $name,
        private string $image,
        private int $replicas,
        private int $cpuMhz,
        private int $memoryMb,
        private ?string $domain,
        private ?array $portMappings,
        private ?array $envVars,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        return new self(
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            name: (string) ($attributes['name'] ?? ''),
            image: (string) ($attributes['image'] ?? ''),
            replicas: (int) ($attributes['replicas'] ?? 1),
            cpuMhz: (int) ($attributes['cpu_mhz'] ?? 100),
            memoryMb: (int) ($attributes['memory_mb'] ?? 128),
            domain: isset($attributes['domain']) ? (string) $attributes['domain'] : null,
            portMappings: isset($attributes['port_mappings']) && is_array($attributes['port_mappings'])
                ? $attributes['port_mappings']
                : null,
            envVars: isset($attributes['env_vars']) && is_array($attributes['env_vars'])
                ? $attributes['env_vars']
                : null,
        );
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getReplicas(): int
    {
        return $this->replicas;
    }

    public function getCpuMhz(): int
    {
        return $this->cpuMhz;
    }

    public function getMemoryMb(): int
    {
        return $this->memoryMb;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getPortMappings(): ?array
    {
        return $this->portMappings;
    }

    /**
     * @return array<string, string>|null
     */
    public function getEnvVars(): ?array
    {
        return $this->envVars;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'image' => $this->image,
            'replicas' => $this->replicas,
            'cpu_mhz' => $this->cpuMhz,
            'memory_mb' => $this->memoryMb,
            'domain' => $this->domain,
            'port_mappings' => $this->portMappings,
            'env_vars' => $this->envVars,
        ];
    }
}
