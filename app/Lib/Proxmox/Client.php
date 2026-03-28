<?php

declare(strict_types=1);

namespace App\Lib\Proxmox;

use App\Lib\Proxmox\Exceptions\ProxmoxApiException;
use App\Lib\Proxmox\Exceptions\ProxmoxAuthException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Client
{
    private string $baseUrl;

    public function __construct(
        private readonly string $hostname,
        private readonly string $tokenId,
        private readonly string $tokenSecret,
        private readonly bool $verifyTls = true,
    ) {
        $this->baseUrl = "https://{$hostname}:8006/api2/json";
    }

    public function get(string $path, array $params = []): array
    {
        $response = $this->request()->get($this->url($path), $params);

        return $this->handleResponse($response);
    }

    public function post(string $path, array $data = []): array
    {
        $response = $this->request()->post($this->url($path), $data);

        return $this->handleResponse($response);
    }

    public function put(string $path, array $data = []): array
    {
        $response = $this->request()->put($this->url($path), $data);

        return $this->handleResponse($response);
    }

    public function delete(string $path): array
    {
        $response = $this->request()->delete($this->url($path));

        return $this->handleResponse($response);
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $http = Http::withHeaders([
            'Authorization' => "PVEAPIToken={$this->tokenId}={$this->tokenSecret}",
        ]);

        if (! $this->verifyTls) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    private function handleResponse(Response $response): array
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProxmoxAuthException(
                "Proxmox authentication failed: {$response->status()}",
                $response->status(),
            );
        }

        if ($response->failed()) {
            throw new ProxmoxApiException(
                "Proxmox API error: {$response->status()} - {$response->body()}",
                $response->status(),
            );
        }

        return $response->json('data') ?? [];
    }
}
