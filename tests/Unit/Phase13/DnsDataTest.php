<?php

declare(strict_types=1);

use App\Data\Dns\DnsRecordData;
use App\Data\Dns\DnsZoneData;
use App\Models\DnsRecord;
use App\Models\DnsZone;

test('dns zone data converts with of and toArray', function (): void {
    $zone = new DnsZone();
    $zone->id = 10;
    $zone->name = 'infra.example.com';
    $zone->provider = 'sakura';
    $zone->external_zone_id = 'zone-001';
    $zone->description = 'infra zone';
    $zone->is_active = true;

    $data = DnsZoneData::of($zone);

    expect($data->getId())->toBe(10)
        ->and($data->getName())->toBe('infra.example.com')
        ->and($data->getProvider())->toBe('sakura')
        ->and($data->getExternalZoneId())->toBe('zone-001')
        ->and($data->getDescription())->toBe('infra zone')
        ->and($data->isActive())->toBeTrue()
        ->and($data->toArray())->toBe([
            'name' => 'infra.example.com',
            'provider' => 'sakura',
            'external_zone_id' => 'zone-001',
            'description' => 'infra zone',
            'is_active' => true,
        ]);
});

test('dns zone data can be built from array', function (): void {
    $data = DnsZoneData::make([
        'id' => 11,
        'name' => 'local.override',
        'provider' => 'local',
        'external_zone_id' => null,
        'description' => 'local split horizon',
        'is_active' => false,
    ]);

    expect($data->getId())->toBe(11)
        ->and($data->getName())->toBe('local.override')
        ->and($data->getProvider())->toBe('local')
        ->and($data->getExternalZoneId())->toBeNull()
        ->and($data->getDescription())->toBe('local split horizon')
        ->and($data->isActive())->toBeFalse();
});

test('dns record data converts with of and toArray', function (): void {
    $record = new DnsRecord();
    $record->id = 22;
    $record->dns_zone_id = 10;
    $record->name = 'registry';
    $record->type = 'A';
    $record->content = '172.26.26.10';
    $record->ttl = 300;
    $record->priority = null;
    $record->external_id = 'record-xyz';
    $record->comment = 'internal service';

    $data = DnsRecordData::of($record);

    expect($data->getId())->toBe(22)
        ->and($data->getDnsZoneId())->toBe(10)
        ->and($data->getName())->toBe('registry')
        ->and($data->getType())->toBe('A')
        ->and($data->getContent())->toBe('172.26.26.10')
        ->and($data->getTtl())->toBe(300)
        ->and($data->getPriority())->toBeNull()
        ->and($data->getExternalId())->toBe('record-xyz')
        ->and($data->getComment())->toBe('internal service')
        ->and($data->toArray())->toBe([
            'dns_zone_id' => 10,
            'name' => 'registry',
            'type' => 'A',
            'content' => '172.26.26.10',
            'ttl' => 300,
            'priority' => null,
            'external_id' => 'record-xyz',
            'comment' => 'internal service',
        ]);
});

test('dns record data can be built from array', function (): void {
    $data = DnsRecordData::make([
        'id' => 23,
        'dns_zone_id' => 11,
        'name' => 'mail',
        'type' => 'MX',
        'content' => 'mail.example.com',
        'ttl' => 600,
        'priority' => 10,
        'external_id' => 'record-abc',
        'comment' => 'mail route',
    ]);

    expect($data->getId())->toBe(23)
        ->and($data->getDnsZoneId())->toBe(11)
        ->and($data->getName())->toBe('mail')
        ->and($data->getType())->toBe('MX')
        ->and($data->getContent())->toBe('mail.example.com')
        ->and($data->getTtl())->toBe(600)
        ->and($data->getPriority())->toBe(10)
        ->and($data->getExternalId())->toBe('record-abc')
        ->and($data->getComment())->toBe('mail route');
});
