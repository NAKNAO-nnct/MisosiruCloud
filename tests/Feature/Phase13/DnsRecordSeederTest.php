<?php

declare(strict_types=1);

use App\Models\DnsRecord;
use App\Models\DnsZone;
use Database\Seeders\DnsRecordSeeder;

it('seeds phase13 initial dns records for global infra and local override zones', function (): void {
    $this->seed(DnsRecordSeeder::class);

    $globalZone = DnsZone::query()->where('name', 'example.com')->firstOrFail();
    $infraZone = DnsZone::query()->where('name', 'infra.example.com')->firstOrFail();
    $localZone = DnsZone::query()->where('name', 'local.override')->firstOrFail();

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $globalZone->id,
        'name' => '@',
        'type' => 'A',
        'content' => '198.51.100.10',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $globalZone->id,
        'name' => '*.containers',
        'type' => 'A',
        'content' => '198.51.100.10',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $globalZone->id,
        'name' => 'infra',
        'type' => 'NS',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $infraZone->id,
        'name' => 'mgmt',
        'type' => 'A',
        'content' => '172.26.26.10',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $infraZone->id,
        'name' => 'snippet-pve3',
        'type' => 'A',
        'content' => '172.26.26.13',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $localZone->id,
        'name' => 'registry.example.com',
        'type' => 'A',
        'content' => '172.26.26.10',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $localZone->id,
        'name' => 'mgmt.example.com',
        'type' => 'A',
        'content' => '172.26.26.10',
    ]);
});

it('does not duplicate phase13 initial dns records on repeated runs', function (): void {
    $this->seed(DnsRecordSeeder::class);
    $this->seed(DnsRecordSeeder::class);

    $globalZone = DnsZone::query()->where('name', 'example.com')->firstOrFail();
    $infraZone = DnsZone::query()->where('name', 'infra.example.com')->firstOrFail();
    $localZone = DnsZone::query()->where('name', 'local.override')->firstOrFail();

    expect(DnsRecord::query()->where('dns_zone_id', $globalZone->id)->count())->toBe(3)
        ->and(DnsRecord::query()->where('dns_zone_id', $infraZone->id)->count())->toBe(8)
        ->and(DnsRecord::query()->where('dns_zone_id', $localZone->id)->count())->toBe(2)
        ->and(DnsRecord::query()->count())->toBe(13);
});
