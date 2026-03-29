<?php

declare(strict_types=1);

namespace App\Lib\Dns;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareDnsProvider implements DnsProviderInterface
{
    public function __construct(
        private readonly string $apiToken,
        private readonly string $zoneId,
    ) {
    }

    public function listRecords(): array
    {
        $this->ensureConfigured();

        $response = $this->request()->get($this->url('/zones/' . $this->zoneId . '/dns_records'));

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch Cloudflare DNS records.');
        }

        $records = $response->json('result');

        return is_array($records) ? $records : [];
    }

    public function createRecord(array $params): array
    {
        $this->ensureConfigured();

        $response = $this->request()->post($this->url('/zones/' . $this->zoneId . '/dns_records'), $params);

        if ($response->failed()) {
            throw new RuntimeException('Failed to create Cloudflare DNS record.');
        }

        $record = $response->json('result');

        return is_array($record) ? $record : [];
    }

    public function updateRecord(string $recordId, array $params): array
    {
        $this->ensureConfigured();

        $response = $this->request()->put($this->url('/zones/' . $this->zoneId . '/dns_records/' . $recordId), $params);

        if ($response->failed()) {
            throw new RuntimeException('Failed to update Cloudflare DNS record.');
        }

        $record = $response->json('result');

        return is_array($record) ? $record : [];
    }

    public function deleteRecord(string $recordId): void
    {
        $this->ensureConfigured();

        $response = $this->request()->delete($this->url('/zones/' . $this->zoneId . '/dns_records/' . $recordId));

        if ($response->failed()) {
            throw new RuntimeException('Failed to delete Cloudflare DNS record.');
        }
    }

    private function ensureConfigured(): void
    {
        if ($this->apiToken === '' || $this->zoneId === '') {
            throw new RuntimeException('Cloudflare DNS configuration is missing.');
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return 'https://api.cloudflare.com/client/v4' . '/' . mb_ltrim($path, '/');
    }
}
