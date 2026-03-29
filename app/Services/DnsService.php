<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Dns\DnsRecordData;
use App\Data\Dns\DnsZoneData;
use App\Lib\Dns\DnsProviderFactory;
use App\Lib\Dns\DnsProviderInterface;
use App\Lib\Dns\LocalDnsProvider;
use App\Repositories\DnsRecordRepository;
use App\Repositories\DnsZoneRepository;
use Illuminate\Support\Collection;
use RuntimeException;

class DnsService
{
    public function __construct(
        private readonly DnsZoneRepository $dnsZoneRepository,
        private readonly DnsRecordRepository $dnsRecordRepository,
        private readonly DnsProviderFactory $dnsProviderFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function createRecord(int $zoneId, array $attributes): DnsRecordData
    {
        $zone = $this->dnsZoneRepository->findByIdOrFail($zoneId);
        $provider = $this->providerForZone($zone);

        $recordPayload = $this->normalizeRecordPayload($zoneId, $attributes);
        $providerResponse = $provider->createRecord($this->providerPayload($recordPayload));

        $recordPayload['external_id'] = $this->extractExternalId($providerResponse);

        return $this->dnsRecordRepository->create($recordPayload);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateRecord(int $zoneId, int $recordId, array $attributes): DnsRecordData
    {
        $zone = $this->dnsZoneRepository->findByIdOrFail($zoneId);
        $record = $this->dnsRecordRepository->findByIdOrFail($recordId);

        if ($record->getDnsZoneId() !== $zone->getId()) {
            throw new RuntimeException('DNS record does not belong to the given zone.');
        }

        $provider = $this->providerForZone($zone);

        $updatedPayload = array_merge($record->toArray(), $this->normalizeRecordPayload($zoneId, $attributes));
        $providerRecordId = $record->getExternalId() ?: (string) $record->getId();

        $providerResponse = $provider->updateRecord($providerRecordId, $this->providerPayload($updatedPayload));
        $externalId = $this->extractExternalId($providerResponse);

        if ($externalId !== null) {
            $updatedPayload['external_id'] = $externalId;
        }

        return $this->dnsRecordRepository->update($recordId, $updatedPayload);
    }

    public function deleteRecord(int $zoneId, int $recordId): void
    {
        $zone = $this->dnsZoneRepository->findByIdOrFail($zoneId);
        $record = $this->dnsRecordRepository->findByIdOrFail($recordId);

        if ($record->getDnsZoneId() !== $zone->getId()) {
            throw new RuntimeException('DNS record does not belong to the given zone.');
        }

        $provider = $this->providerForZone($zone);
        $providerRecordId = $record->getExternalId() ?: (string) $record->getId();

        $provider->deleteRecord($providerRecordId);
        $this->dnsRecordRepository->delete($recordId);
    }

    /**
     * @return Collection<int, DnsRecordData>
     */
    public function syncFromProvider(int $zoneId): Collection
    {
        $zone = $this->dnsZoneRepository->findByIdOrFail($zoneId);
        $provider = $this->providerForZone($zone);

        $remoteRecords = $provider->listRecords();
        $existingRecords = $this->dnsRecordRepository->findByZoneId($zoneId);

        foreach ($existingRecords as $existingRecord) {
            $this->dnsRecordRepository->delete($existingRecord->getId());
        }

        $synced = collect();

        foreach ($remoteRecords as $remoteRecord) {
            if (!is_array($remoteRecord)) {
                continue;
            }

            $payload = [
                'dns_zone_id' => $zoneId,
                'name' => (string) ($remoteRecord['name'] ?? '@'),
                'type' => (string) ($remoteRecord['type'] ?? 'A'),
                'content' => (string) ($remoteRecord['content'] ?? $remoteRecord['value'] ?? ''),
                'ttl' => (int) ($remoteRecord['ttl'] ?? 300),
                'priority' => isset($remoteRecord['priority']) ? (int) $remoteRecord['priority'] : null,
                'external_id' => isset($remoteRecord['id']) ? (string) $remoteRecord['id'] : null,
                'comment' => isset($remoteRecord['comment']) ? (string) $remoteRecord['comment'] : null,
            ];

            $synced->push($this->dnsRecordRepository->create($payload));
        }

        return $synced;
    }

    public function regenerateLocalZones(): void
    {
        $localZones = $this->dnsZoneRepository->findByProvider('local');

        if ($localZones->isEmpty()) {
            return;
        }

        $zoneNames = collect();
        $localProvider = null;

        foreach ($localZones as $zone) {
            $provider = $this->providerForZone($zone);

            if (!$provider instanceof LocalDnsProvider) {
                throw new RuntimeException('Local provider is required for local zones.');
            }

            $records = $this->dnsRecordRepository
                ->findByZoneId($zone->getId())
                ->map(fn (DnsRecordData $record): array => [
                    'name' => $record->getName(),
                    'type' => $record->getType(),
                    'content' => $record->getContent(),
                    'ttl' => $record->getTtl(),
                    'priority' => $record->getPriority(),
                ]);

            $provider->regenerateZoneFile($zone->getName(), $records);

            $zoneNames->push($zone->getName());
            $localProvider = $provider;
        }

        if ($localProvider instanceof LocalDnsProvider) {
            $localProvider->regenerateCorefile($zoneNames);
            $localProvider->reloadCoreDns();
        }
    }

    private function providerForZone(DnsZoneData $zone): DnsProviderInterface
    {
        return $this->dnsProviderFactory->make(
            provider: $zone->getProvider(),
            zoneName: $zone->getName(),
            externalZoneId: $zone->getExternalZoneId(),
        );
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function normalizeRecordPayload(int $zoneId, array $attributes): array
    {
        $payload = [
            'dns_zone_id' => $zoneId,
            'name' => (string) ($attributes['name'] ?? '@'),
            'type' => (string) ($attributes['type'] ?? 'A'),
            'content' => (string) ($attributes['content'] ?? $attributes['value'] ?? ''),
            'ttl' => (int) ($attributes['ttl'] ?? 300),
            'priority' => isset($attributes['priority']) ? (int) $attributes['priority'] : null,
            'comment' => isset($attributes['comment']) ? (string) $attributes['comment'] : null,
        ];

        if (isset($attributes['external_id'])) {
            $payload['external_id'] = (string) $attributes['external_id'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function providerPayload(array $payload): array
    {
        return [
            'name' => $payload['name'],
            'type' => $payload['type'],
            'content' => $payload['content'],
            'value' => $payload['content'],
            'ttl' => $payload['ttl'],
            'priority' => $payload['priority'] ?? null,
            'comment' => $payload['comment'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractExternalId(array $response): ?string
    {
        if (!isset($response['id'])) {
            return null;
        }

        return (string) $response['id'];
    }
}
