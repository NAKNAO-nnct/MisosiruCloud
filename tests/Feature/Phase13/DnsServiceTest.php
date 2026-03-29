<?php

declare(strict_types=1);

use App\Lib\Dns\DnsProviderFactory;
use App\Lib\Dns\DnsProviderInterface;
use App\Lib\Dns\LocalDnsProvider;
use App\Models\DnsRecord;
use App\Models\DnsZone;
use App\Repositories\DnsRecordRepository;
use App\Repositories\DnsZoneRepository;
use App\Services\DnsService;

test('dns service creates record and forwards to provider', function (): void {
    $zone = DnsZone::factory()->create([
        'provider' => 'sakura',
        'name' => 'infra.example.com',
    ]);

    $provider = new class() implements DnsProviderInterface
    {
        public array $createdPayloads = [];

        public function listRecords(): array
        {
            return [];
        }

        public function createRecord(array $params): array
        {
            $this->createdPayloads[] = $params;

            return ['id' => 'ext-1'];
        }

        public function updateRecord(string $recordId, array $params): array
        {
            return [];
        }

        public function deleteRecord(string $recordId): void
        {
        }
    };

    $factory = new class($provider) extends DnsProviderFactory
    {
        public function __construct(private readonly DnsProviderInterface $provider)
        {
            parent::__construct([]);
        }

        public function make(string $provider, string $zoneName, ?string $externalZoneId = null): DnsProviderInterface
        {
            return $this->provider;
        }
    };

    $service = new DnsService(
        dnsZoneRepository: app(DnsZoneRepository::class),
        dnsRecordRepository: app(DnsRecordRepository::class),
        dnsProviderFactory: $factory,
    );

    $record = $service->createRecord($zone->id, [
        'name' => 'mgmt',
        'type' => 'A',
        'content' => '172.26.26.10',
        'ttl' => 300,
    ]);

    expect($provider->createdPayloads)->toHaveCount(1)
        ->and($record->getExternalId())->toBe('ext-1');

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $zone->id,
        'name' => 'mgmt',
        'external_id' => 'ext-1',
    ]);
});

test('dns service updates and deletes record through provider', function (): void {
    $zone = DnsZone::factory()->create([
        'provider' => 'sakura',
        'name' => 'infra.example.com',
    ]);
    $record = DnsRecord::factory()->for($zone, 'zone')->create([
        'name' => 'mgmt',
        'type' => 'A',
        'content' => '172.26.26.10',
        'external_id' => 'ext-5',
    ]);

    $provider = new class() implements DnsProviderInterface
    {
        public array $updated = [];

        public array $deleted = [];

        public function listRecords(): array
        {
            return [];
        }

        public function createRecord(array $params): array
        {
            return [];
        }

        public function updateRecord(string $recordId, array $params): array
        {
            $this->updated[] = ['record_id' => $recordId, 'params' => $params];

            return ['id' => $recordId];
        }

        public function deleteRecord(string $recordId): void
        {
            $this->deleted[] = $recordId;
        }
    };

    $factory = new class($provider) extends DnsProviderFactory
    {
        public function __construct(private readonly DnsProviderInterface $provider)
        {
            parent::__construct([]);
        }

        public function make(string $provider, string $zoneName, ?string $externalZoneId = null): DnsProviderInterface
        {
            return $this->provider;
        }
    };

    $service = new DnsService(
        dnsZoneRepository: app(DnsZoneRepository::class),
        dnsRecordRepository: app(DnsRecordRepository::class),
        dnsProviderFactory: $factory,
    );

    $updated = $service->updateRecord($zone->id, $record->id, [
        'content' => '172.26.26.11',
        'ttl' => 600,
    ]);

    expect($provider->updated)->toHaveCount(1)
        ->and($provider->updated[0]['record_id'])->toBe('ext-5')
        ->and($updated->getContent())->toBe('172.26.26.11');

    $service->deleteRecord($zone->id, $record->id);

    expect($provider->deleted)->toHaveCount(1)
        ->and($provider->deleted[0])->toBe('ext-5');

    $this->assertDatabaseMissing('dns_records', ['id' => $record->id]);
});

test('dns service syncs records from provider', function (): void {
    $zone = DnsZone::factory()->create([
        'provider' => 'sakura',
        'name' => 'infra.example.com',
    ]);

    DnsRecord::factory()->for($zone, 'zone')->create([
        'name' => 'old',
        'type' => 'A',
        'content' => '192.0.2.1',
    ]);

    $provider = new class() implements DnsProviderInterface
    {
        public function listRecords(): array
        {
            return [
                ['id' => 'r1', 'name' => 'mgmt', 'type' => 'A', 'value' => '172.26.26.10', 'ttl' => 300],
                ['id' => 'r2', 'name' => 'mail', 'type' => 'MX', 'value' => 'mail.infra.example.com.', 'ttl' => 300, 'priority' => 10],
            ];
        }

        public function createRecord(array $params): array
        {
            return [];
        }

        public function updateRecord(string $recordId, array $params): array
        {
            return [];
        }

        public function deleteRecord(string $recordId): void
        {
        }
    };

    $factory = new class($provider) extends DnsProviderFactory
    {
        public function __construct(private readonly DnsProviderInterface $provider)
        {
            parent::__construct([]);
        }

        public function make(string $provider, string $zoneName, ?string $externalZoneId = null): DnsProviderInterface
        {
            return $this->provider;
        }
    };

    $service = new DnsService(
        dnsZoneRepository: app(DnsZoneRepository::class),
        dnsRecordRepository: app(DnsRecordRepository::class),
        dnsProviderFactory: $factory,
    );

    $synced = $service->syncFromProvider($zone->id);

    expect($synced)->toHaveCount(2);

    $this->assertDatabaseMissing('dns_records', [
        'dns_zone_id' => $zone->id,
        'name' => 'old',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $zone->id,
        'name' => 'mgmt',
        'content' => '172.26.26.10',
        'external_id' => 'r1',
    ]);
});

test('dns service regenerates local zones via local provider', function (): void {
    $basePath = sys_get_temp_dir() . '/misosiru-dns-service-' . uniqid();
    $zonesPath = $basePath . '/zones';
    $corefilePath = $basePath . '/Corefile';

    $zone = DnsZone::factory()->create([
        'provider' => 'local',
        'name' => 'local.override',
    ]);

    DnsRecord::factory()->for($zone, 'zone')->create([
        'name' => 'registry',
        'type' => 'A',
        'content' => '172.26.26.10',
    ]);

    $localProvider = new class($zonesPath, $corefilePath) extends LocalDnsProvider
    {
        public bool $reloaded = false;

        public function __construct(string $zonesPath, string $corefilePath)
        {
            parent::__construct($zonesPath, $corefilePath, 'dns');
        }

        public function reloadCoreDns(): void
        {
            $this->reloaded = true;
        }
    };

    $factory = new class($localProvider) extends DnsProviderFactory
    {
        public function __construct(private readonly DnsProviderInterface $provider)
        {
            parent::__construct([]);
        }

        public function make(string $provider, string $zoneName, ?string $externalZoneId = null): DnsProviderInterface
        {
            return $this->provider;
        }
    };

    $service = new DnsService(
        dnsZoneRepository: app(DnsZoneRepository::class),
        dnsRecordRepository: app(DnsRecordRepository::class),
        dnsProviderFactory: $factory,
    );

    $service->regenerateLocalZones();

    expect(is_file($zonesPath . '/db.local.override'))->toBeTrue()
        ->and(is_file($corefilePath))->toBeTrue()
        ->and($localProvider->reloaded)->toBeTrue();
});
