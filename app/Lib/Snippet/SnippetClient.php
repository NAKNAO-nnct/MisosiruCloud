<?php

declare(strict_types=1);

namespace App\Lib\Snippet;

use App\Lib\Snippet\Exceptions\SnippetApiException;
use Illuminate\Support\Facades\Http;

class SnippetClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {
    }

    public function upload(int $vmId, string $userData, ?string $networkConfig = null, ?string $metaData = null): void
    {
        $this->validateVmId($vmId);

        $response = $this->request()->post($this->url('/snippets/' . $vmId), [
            'user_data' => $userData,
            'network_config' => $networkConfig,
            'meta_data' => $metaData,
        ]);

        if ($response->failed()) {
            throw new SnippetApiException('Snippet upload failed: ' . $response->status() . ' ' . $response->body());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $vmId): array
    {
        $this->validateVmId($vmId);

        $response = $this->request()->get($this->url('/snippets/' . $vmId));

        if ($response->failed()) {
            throw new SnippetApiException('Snippet fetch failed: ' . $response->status() . ' ' . $response->body());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    public function delete(int $vmId): void
    {
        $this->validateVmId($vmId);

        $response = $this->request()->delete($this->url('/snippets/' . $vmId));

        if ($response->failed()) {
            throw new SnippetApiException('Snippet delete failed: ' . $response->status() . ' ' . $response->body());
        }
    }

    private function validateVmId(int $vmId): void
    {
        if ($vmId < 100 || $vmId > 999_999_999) {
            throw new SnippetApiException('Invalid vm_id.');
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($this->token);
    }

    private function url(string $path): string
    {
        return mb_rtrim($this->baseUrl, '/') . '/' . mb_ltrim($path, '/');
    }
}
