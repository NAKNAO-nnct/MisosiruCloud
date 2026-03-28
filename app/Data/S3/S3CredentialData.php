<?php

declare(strict_types=1);

namespace App\Data\S3;

use App\Models\S3Credential;
use DateTimeImmutable;

final readonly class S3CredentialData
{
    private function __construct(
        private int $id,
        private int $tenantId,
        private string $accessKey,
        private string $secretKey,
        private string $allowedBucket,
        private string $allowedPrefix,
        private string $description,
        private bool $isActive,
        private ?DateTimeImmutable $lastUsedAt,
        private ?DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Eloquent Model から生成 (Repository 内部でのみ使用).
     */
    public static function of(S3Credential $model): self
    {
        return new self(
            id: $model->id,
            tenantId: (int) $model->tenant_id,
            accessKey: $model->access_key,
            secretKey: $model->secret_key_plain ?? '',
            allowedBucket: $model->allowed_bucket,
            allowedPrefix: $model->allowed_prefix,
            description: $model->description ?? '',
            isActive: (bool) $model->is_active,
            lastUsedAt: $model->last_used_at
                ? DateTimeImmutable::createFromInterface($model->last_used_at)
                : null,
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
            accessKey: (string) ($attributes['access_key'] ?? ''),
            secretKey: (string) ($attributes['secret_key'] ?? $attributes['secret_key_plain'] ?? ''),
            allowedBucket: (string) ($attributes['allowed_bucket'] ?? ''),
            allowedPrefix: (string) ($attributes['allowed_prefix'] ?? ''),
            description: (string) ($attributes['description'] ?? ''),
            isActive: (bool) ($attributes['is_active'] ?? true),
            lastUsedAt: null,
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

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getAllowedBucket(): string
    {
        return $this->allowedBucket;
    }

    public function getAllowedPrefix(): string
    {
        return $this->allowedPrefix;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
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
            'access_key' => $this->accessKey,
            'secret_key_plain' => $this->secretKey,
            'allowed_bucket' => $this->allowedBucket,
            'allowed_prefix' => $this->allowedPrefix,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
