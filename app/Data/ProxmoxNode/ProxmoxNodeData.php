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
            apiTokenSecret: $model->api_token_secret_encrypted,
            snippetApiUrl: $model->snippet_api_url,
            snippetApiToken: $model->snippet_api_token_encrypted,
            isActive: (bool) $model->is_active,
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
}
