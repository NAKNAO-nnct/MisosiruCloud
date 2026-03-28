<?php

declare(strict_types=1);

namespace App\Lib\Proxmox;

use App\Lib\Proxmox\Exceptions\ProxmoxApiException;
use App\Lib\Proxmox\Exceptions\ProxmoxAuthException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class Client
{
    private string $baseUrl;

    public function __construct(
        private readonly string $hostname,
        private readonly string $tokenId,
        private readonly string $tokenSecret,
        private readonly bool $verifyTls = true,
    ) {
        $this->baseUrl = $this->resolveBaseUrl($hostname);
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

        if (!$this->verifyTls) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/' . mb_ltrim($path, '/');
    }

    private function handleResponse(Response $response): array
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProxmoxAuthException("Proxmox authentication failed: {$response->status()}", $response->status());
        }

        if ($response->failed()) {
            throw new ProxmoxApiException("Proxmox API error: {$response->status()} - {$response->body()}", $response->status());
        }

        $data = $response->json('data');

        if (is_array($data)) {
            return $data;
        }

        if ($data === null) {
            return [];
        }

        if (is_string($data) && str_starts_with($data, 'UPID:')) {
            return [
                'upid' => $data,
                'data' => $data,
            ];
        }

        return ['data' => $data];
    }

    private function resolveBaseUrl(string $hostname): string
    {
        $input = mb_trim($hostname);

        if ($input === '') {
            throw new InvalidArgumentException('Proxmox hostname must not be empty.');
        }

        $endpoint = str_starts_with($input, 'http://') || str_starts_with($input, 'https://')
            ? $input
            : "https://{$input}";

        $parts = parse_url($endpoint);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if ($host === '') {
            throw new InvalidArgumentException('Invalid Proxmox hostname.');
        }

        $port = $parts['port'] ?? 8006;

        return "{$scheme}://{$host}:{$port}/api2/json";
    }
}
