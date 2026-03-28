<?php

declare(strict_types=1);

namespace App\Data\VpsGateway;

use App\Models\VpsGateway;
use DateTimeImmutable;

final readonly class VpsGatewayData
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    private function __construct(
        private int $id,
        private string $name,
        private string $globalIp,
        private string $wireguardIp,
        private int $wireguardPort,
        private string $wireguardPublicKey,
        private int $transitWireguardPort,
        private string $status,
        private ?string $purpose,
        private ?array $metadata,
        private ?DateTimeImmutable $createdAt,
    ) {
    }

    public static function of(VpsGateway $model): self
    {
        return new self(
            id: $model->id,
            name: (string) $model->name,
            globalIp: (string) $model->global_ip,
            wireguardIp: (string) $model->wireguard_ip,
            wireguardPort: (int) $model->wireguard_port,
            wireguardPublicKey: (string) $model->wireguard_public_key,
            transitWireguardPort: (int) $model->transit_wireguard_port,
            status: (string) $model->status,
            purpose: $model->purpose,
            metadata: is_array($model->metadata) ? $model->metadata : null,
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
            name: (string) ($attributes['name'] ?? ''),
            globalIp: (string) ($attributes['global_ip'] ?? ''),
            wireguardIp: (string) ($attributes['wireguard_ip'] ?? ''),
            wireguardPort: (int) ($attributes['wireguard_port'] ?? 51820),
            wireguardPublicKey: (string) ($attributes['wireguard_public_key'] ?? ''),
            transitWireguardPort: (int) ($attributes['transit_wireguard_port'] ?? 51821),
            status: (string) ($attributes['status'] ?? 'active'),
            purpose: isset($attributes['purpose']) ? (string) $attributes['purpose'] : null,
            metadata: isset($attributes['metadata']) && is_array($attributes['metadata'])
                ? $attributes['metadata']
                : null,
            createdAt: null,
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

    public function getGlobalIp(): string
    {
        return $this->globalIp;
    }

    public function getWireguardIp(): string
    {
        return $this->wireguardIp;
    }

    public function getWireguardPort(): int
    {
        return $this->wireguardPort;
    }

    public function getWireguardPublicKey(): string
    {
        return $this->wireguardPublicKey;
    }

    public function getTransitWireguardPort(): int
    {
        return $this->transitWireguardPort;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
