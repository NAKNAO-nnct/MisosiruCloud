<?php

declare(strict_types=1);

namespace App\Data\ProxmoxNode;

use App\Models\ProxmoxNode;

final readonly class ProxmoxNodeData
{
    private function __construct(
        private int $id,
        private string $name,
        private string $hostname,
        private string $apiTokenId,
        private string $apiTokenSecret,
        private string $snippetApiUrl,
        private string $snippetApiToken,
        private bool $isActive,
    ) {
    }

    /**
     * Eloquent Model から生成 (Repository 内部でのみ使用).
     */
    public static function of(ProxmoxNode $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            hostname: $model->hostname,
            apiTokenId: $model->api_token_id,
            apiTokenSecret: (string) $model->api_token_secret_encrypted,
            snippetApiUrl: $model->snippet_api_url,
            snippetApiToken: (string) $model->snippet_api_token_encrypted,
            isActive: (bool) $model->is_active,
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
            hostname: (string) ($attributes['hostname'] ?? ''),
            apiTokenId: (string) ($attributes['api_token_id'] ?? ''),
            apiTokenSecret: (string) ($attributes['api_token_secret_encrypted'] ?? ''),
            snippetApiUrl: (string) ($attributes['snippet_api_url'] ?? ''),
            snippetApiToken: (string) ($attributes['snippet_api_token_encrypted'] ?? ''),
            isActive: (bool) ($attributes['is_active'] ?? false),
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

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getApiTokenId(): string
    {
        return $this->apiTokenId;
    }

    public function getApiTokenSecret(): string
    {
        return $this->apiTokenSecret;
    }

    public function getSnippetApiUrl(): string
    {
        return $this->snippetApiUrl;
    }

    public function getSnippetApiToken(): string
    {
        return $this->snippetApiToken;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'api_token_id' => $this->apiTokenId,
            'api_token_secret_encrypted' => $this->apiTokenSecret,
            'snippet_api_url' => $this->snippetApiUrl,
            'snippet_api_token_encrypted' => $this->snippetApiToken,
            'is_active' => $this->isActive,
        ];
    }
}
