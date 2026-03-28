<?php

declare(strict_types=1);

namespace App\Lib\Nomad;

use App\Lib\Nomad\Exceptions\NomadApiException;
use App\Lib\Nomad\Exceptions\NomadAuthException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class Client
{
    private string $baseUrl;

    public function __construct(
        private readonly string $address,
        private readonly string $token,
        private readonly bool $verifyTls = true,
    ) {
        $this->baseUrl = $this->resolveBaseUrl($this->address);
    }

    public function get(string $path, array $params = []): array
    {
        $response = $this->request()->get($this->url($path), $params);

        return $this->handleResponse($response);
    }

    public function getRaw(string $path, array $params = []): string
    {
        $response = $this->request()->get($this->url($path), $params);

        $this->handleFailure($response);

        return $response->body();
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

    public function delete(string $path, array $params = []): array
    {
        $response = $this->request()->delete($this->url($path), $params);

        return $this->handleResponse($response);
    }

    private function request(): PendingRequest
    {
        $http = Http::acceptJson()->asJson()->withToken($this->token);

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
        $this->handleFailure($response);

        $data = $response->json();

        if (is_array($data)) {
            return $data;
        }

        if ($data === null) {
            return [];
        }

        return ['data' => $data];
    }

    private function handleFailure(Response $response): void
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new NomadAuthException("Nomad authentication failed: {$response->status()}", $response->status());
        }

        if ($response->failed()) {
            throw new NomadApiException("Nomad API error: {$response->status()} - {$response->body()}", $response->status());
        }
    }

    private function resolveBaseUrl(string $address): string
    {
        $input = mb_trim($address);

        if ($input === '') {
            throw new InvalidArgumentException('Nomad address must not be empty.');
        }

        $endpoint = str_starts_with($input, 'http://') || str_starts_with($input, 'https://')
            ? $input
            : "http://{$input}";

        $parts = parse_url($endpoint);

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';

        if ($host === '') {
            throw new InvalidArgumentException('Invalid Nomad address.');
        }

        $port = $parts['port'] ?? 4646;

        return "{$scheme}://{$host}:{$port}/v1";
    }
}
