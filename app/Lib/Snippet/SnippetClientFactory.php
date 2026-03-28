<?php

declare(strict_types=1);

namespace App\Lib\Snippet;

use App\Models\ProxmoxNode;
use RuntimeException;

class SnippetClientFactory
{
    public function forNodeIfConfigured(string $nodeName): ?SnippetClient
    {
        $node = ProxmoxNode::query()->where('name', $nodeName)->first();

        if (!$node) {
            return null;
        }

        return new SnippetClient(
            baseUrl: (string) $node->snippet_api_url,
            token: (string) $node->snippet_api_token_encrypted,
        );
    }

    public function forNode(string $nodeName): SnippetClient
    {
        $client = $this->forNodeIfConfigured($nodeName);

        if (!$client) {
            throw new RuntimeException("Proxmox node not found for snippet client: {$nodeName}");
        }

        return $client;
    }

    public function forActiveNode(): ?SnippetClient
    {
        $node = ProxmoxNode::query()->where('is_active', true)->first();

        if (!$node) {
            return null;
        }

        return new SnippetClient(
            baseUrl: (string) $node->snippet_api_url,
            token: (string) $node->snippet_api_token_encrypted,
        );
    }
}
