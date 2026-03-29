<?php

declare(strict_types=1);

namespace App\Data\Dns;

use App\Models\DnsZone;

final readonly class DnsZoneData
{
    private function __construct(
        private int $id,
        private string $name,
        private string $provider,
        private ?string $externalZoneId,
        private ?string $description,
        private bool $isActive,
    ) {
    }

    public static function of(DnsZone $model): self
    {
        return new self(
            id: $model->id,
            name: (string) $model->name,
            provider: (string) $model->provider,
            externalZoneId: $model->external_zone_id,
            description: $model->description,
            isActive: (bool) $model->is_active,
        );
    }

    public static function make(array $attributes): self
    {
        return new self(
            id: (int) ($attributes['id'] ?? 0),
            name: (string) ($attributes['name'] ?? ''),
            provider: (string) ($attributes['provider'] ?? ''),
            externalZoneId: isset($attributes['external_zone_id']) ? (string) $attributes['external_zone_id'] : null,
            description: isset($attributes['description']) ? (string) $attributes['description'] : null,
            isActive: (bool) ($attributes['is_active'] ?? true),
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getExternalZoneId(): ?string
    {
        return $this->externalZoneId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'provider' => $this->provider,
            'external_zone_id' => $this->externalZoneId,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
