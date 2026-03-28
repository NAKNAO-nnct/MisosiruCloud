<?php

declare(strict_types=1);

namespace App\Data\Tenant;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use DateTimeImmutable;

final readonly class TenantData
{
    private function __construct(
        private int $id,
        private string $uuid,
        private string $name,
        private string $slug,
        private TenantStatus $status,
        private ?string $vnetName,
        private ?int $vni,
        private ?string $networkCidr,
        private ?string $nomadNamespace,
        private ?DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Eloquent Model から生成 (Repository 内部でのみ使用).
     */
    public static function of(Tenant $model): self
    {
        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            slug: $model->slug,
            status: $model->status,
            vnetName: $model->vnet_name,
            vni: $model->vni,
            networkCidr: $model->network_cidr,
            nomadNamespace: $model->nomad_namespace,
            createdAt: $model->created_at
                ? DateTimeImmutable::createFromInterface($model->created_at)
                : null,
        );
    }

    /**
     * 配列 / リクエストデータから生成 (Controller 等から使用).
     *
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $status = $attributes['status'] ?? TenantStatus::Active;

        return new self(
            id: (int) ($attributes['id'] ?? 0),
            uuid: (string) ($attributes['uuid'] ?? ''),
            name: (string) ($attributes['name'] ?? ''),
            slug: (string) ($attributes['slug'] ?? ''),
            status: $status instanceof TenantStatus
                ? $status
                : TenantStatus::from((string) $status),
            vnetName: isset($attributes['vnet_name']) ? (string) $attributes['vnet_name'] : null,
            vni: isset($attributes['vni']) ? (int) $attributes['vni'] : null,
            networkCidr: isset($attributes['network_cidr']) ? (string) $attributes['network_cidr'] : null,
            nomadNamespace: isset($attributes['nomad_namespace']) ? (string) $attributes['nomad_namespace'] : null,
            createdAt: null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): TenantStatus
    {
        return $this->status;
    }

    public function getVnetName(): ?string
    {
        return $this->vnetName;
    }

    public function getVni(): ?int
    {
        return $this->vni;
    }

    public function getNetworkCidr(): ?string
    {
        return $this->networkCidr;
    }

    public function getNomadNamespace(): ?string
    {
        return $this->nomadNamespace;
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
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'vnet_name' => $this->vnetName,
            'vni' => $this->vni,
            'network_cidr' => $this->networkCidr,
            'nomad_namespace' => $this->nomadNamespace,
        ];
    }
}
