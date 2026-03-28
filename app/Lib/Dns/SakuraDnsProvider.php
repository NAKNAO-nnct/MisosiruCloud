<?php

declare(strict_types=1);

namespace App\Lib\Dns;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SakuraDnsProvider implements DnsProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly string $zone,
    ) {
    }

    public function listRecords(): array
    {
        $this->ensureConfigured();

        $response = $this->request()->get($this->url('/zones/' . $this->zone . '/records'));

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch DNS records.');
        }

        $records = $response->json('records');

        return is_array($records) ? $records : [];
    }

    public function createRecord(array $params): array
    {
        $this->ensureConfigured();

        $response = $this->request()->post($this->url('/zones/' . $this->zone . '/records'), $params);

        if ($response->failed()) {
            throw new RuntimeException('Failed to create DNS record.');
        }

        $record = $response->json('record');

        return is_array($record) ? $record : [];
    }

    public function updateRecord(string $recordId, array $params): array
    {
        $this->ensureConfigured();

        $response = $this->request()->put($this->url('/zones/' . $this->zone . '/records/' . $recordId), $params);

        if ($response->failed()) {
            throw new RuntimeException('Failed to update DNS record.');
        }

        $record = $response->json('record');

        return is_array($record) ? $record : [];
    }

    public function deleteRecord(string $recordId): void
    {
        $this->ensureConfigured();

        $response = $this->request()->delete($this->url('/zones/' . $this->zone . '/records/' . $recordId));

        if ($response->failed()) {
            throw new RuntimeException('Failed to delete DNS record.');
        }
    }

    private function ensureConfigured(): void
    {
        if ($this->baseUrl === '' || $this->apiToken === '' || $this->zone === '') {
            throw new RuntimeException('Sakura DNS configuration is missing.');
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return mb_rtrim($this->baseUrl, '/') . '/' . mb_ltrim($path, '/');
    }
}
