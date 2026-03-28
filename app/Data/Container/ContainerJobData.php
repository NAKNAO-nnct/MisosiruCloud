<?php

declare(strict_types=1);

namespace App\Data\Container;

use App\Models\ContainerJob;
use DateTimeImmutable;

final readonly class ContainerJobData
{
    /**
     * @param array<int, array<string, mixed>>|null $portMappings
     */
    private function __construct(
        private int $id,
        private int $tenantId,
        private string $nomadJobId,
        private string $name,
        private string $image,
        private ?string $domain,
        private int $replicas,
        private int $cpuMhz,
        private int $memoryMb,
        private ?array $portMappings,
        private ?string $envVarsEncrypted,
        private ?DateTimeImmutable $createdAt,
    ) {
    }

    public static function of(ContainerJob $model): self
    {
        return new self(
            id: $model->id,
            tenantId: (int) $model->tenant_id,
            nomadJobId: (string) $model->nomad_job_id,
            name: (string) $model->name,
            image: (string) $model->image,
            domain: $model->domain,
            replicas: (int) $model->replicas,
            cpuMhz: (int) $model->cpu_mhz,
            memoryMb: (int) $model->memory_mb,
            portMappings: is_array($model->port_mappings) ? $model->port_mappings : null,
            envVarsEncrypted: $model->env_vars_encrypted,
            createdAt: $model->created_at
                ? DateTimeImmutable::createFromInterface($model->created_at)
                : null,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        return new self(
            id: (int) ($attributes['id'] ?? 0),
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            nomadJobId: (string) ($attributes['nomad_job_id'] ?? ''),
            name: (string) ($attributes['name'] ?? ''),
            image: (string) ($attributes['image'] ?? ''),
            domain: isset($attributes['domain']) ? (string) $attributes['domain'] : null,
            replicas: (int) ($attributes['replicas'] ?? 1),
            cpuMhz: (int) ($attributes['cpu_mhz'] ?? 100),
            memoryMb: (int) ($attributes['memory_mb'] ?? 128),
            portMappings: isset($attributes['port_mappings']) && is_array($attributes['port_mappings'])
                ? $attributes['port_mappings']
                : null,
            envVarsEncrypted: isset($attributes['env_vars_encrypted']) ? (string) $attributes['env_vars_encrypted'] : null,
            createdAt: null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getNomadJobId(): string
    {
        return $this->nomadJobId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
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

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getPortMappings(): ?array
    {
        return $this->portMappings;
    }

    public function getEnvVarsEncrypted(): ?string
    {
        return $this->envVarsEncrypted;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'nomad_job_id' => $this->nomadJobId,
            'name' => $this->name,
            'image' => $this->image,
            'domain' => $this->domain,
            'replicas' => $this->replicas,
            'cpu_mhz' => $this->cpuMhz,
            'memory_mb' => $this->memoryMb,
            'port_mappings' => $this->portMappings,
            'env_vars_encrypted' => $this->envVarsEncrypted,
        ];
    }
}
