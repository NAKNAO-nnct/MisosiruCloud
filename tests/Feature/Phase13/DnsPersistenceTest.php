<?php

declare(strict_types=1);

use App\Models\DnsRecord;
use App\Models\DnsZone;
use App\Repositories\DnsRecordRepository;
use App\Repositories\DnsZoneRepository;

it('persists dns zones through the repository', function (): void {
    $repository = app(DnsZoneRepository::class);

    $zone = $repository->create([
        'name' => 'infra.example.com',
        'provider' => 'sakura',
        'external_zone_id' => 'zone-001',
        'description' => 'internal infra zone',
        'is_active' => true,
    ]);

    expect($zone->getName())->toBe('infra.example.com')
        ->and($zone->getProvider())->toBe('sakura')
        ->and($repository->findByIdOrFail($zone->getId())->getExternalZoneId())->toBe('zone-001');

    $repository->update($zone->getId(), [
        'description' => 'updated description',
        'is_active' => false,
    ]);

    $updated = $repository->findByIdOrFail($zone->getId());

    expect($updated->getDescription())->toBe('updated description')
        ->and($updated->isActive())->toBeFalse();

    $this->assertDatabaseHas('dns_zones', [
        'name' => 'infra.example.com',
        'provider' => 'sakura',
        'is_active' => false,
    ]);
});

it('filters dns zones by provider', function (): void {
    DnsZone::factory()->create(['name' => 'example.com', 'provider' => 'cloudflare']);
    DnsZone::factory()->create(['name' => 'infra.example.com', 'provider' => 'sakura']);
    DnsZone::factory()->create(['name' => 'local.override', 'provider' => 'local']);

    $zones = app(DnsZoneRepository::class)->findByProvider('sakura');

    expect($zones)->toHaveCount(1)
        ->and($zones->first()?->getName())->toBe('infra.example.com');
});

it('persists dns records through the repository', function (): void {
    $zone = DnsZone::factory()->create(['name' => 'infra.example.com']);
    $repository = app(DnsRecordRepository::class);

    $record = $repository->create([
        'dns_zone_id' => $zone->id,
        'name' => 'mgmt',
        'type' => 'A',
        'content' => '172.26.26.10',
        'ttl' => 300,
        'priority' => null,
        'external_id' => 'record-001',
        'comment' => 'management ui',
    ]);

    expect($record->getDnsZoneId())->toBe($zone->id)
        ->and($record->getContent())->toBe('172.26.26.10');

    $repository->update($record->getId(), [
        'content' => '172.26.26.11',
        'ttl' => 600,
    ]);

    $records = $repository->findByZoneId($zone->id);

    expect($records)->toHaveCount(1)
        ->and($records->first()?->getContent())->toBe('172.26.26.11')
        ->and($records->first()?->getTtl())->toBe(600);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $zone->id,
        'name' => 'mgmt',
        'type' => 'A',
        'content' => '172.26.26.11',
    ]);
});

it('filters dns records by zone and type', function (): void {
    $zone = DnsZone::factory()->create();

    DnsRecord::factory()->for($zone, 'zone')->create([
        'name' => 'app',
        'type' => 'A',
        'content' => '192.0.2.10',
    ]);
    DnsRecord::factory()->for($zone, 'zone')->mx()->create([
        'name' => 'mail',
    ]);

    $records = app(DnsRecordRepository::class)->findByZoneIdAndType($zone->id, 'MX');

    expect($records)->toHaveCount(1)
        ->and($records->first()?->getType())->toBe('MX')
        ->and($records->first()?->getPriority())->toBe(10);
});

it('deletes dns zones with dependent records', function (): void {
    $zone = DnsZone::factory()->create();
    $record = DnsRecord::factory()->for($zone, 'zone')->create();

    app(DnsZoneRepository::class)->delete($zone->id);

    $this->assertDatabaseMissing('dns_zones', ['id' => $zone->id]);
    $this->assertDatabaseMissing('dns_records', ['id' => $record->id]);
});
