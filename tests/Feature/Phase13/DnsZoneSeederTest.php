<?php

declare(strict_types=1);

use App\Models\DnsZone;
use Database\Seeders\DnsZoneSeeder;

it('seeds default phase13 dns zones', function (): void {
    $this->seed(DnsZoneSeeder::class);

    $this->assertDatabaseHas('dns_zones', [
        'name' => 'example.com',
        'provider' => 'cloudflare',
    ]);

    $this->assertDatabaseHas('dns_zones', [
        'name' => 'infra.example.com',
        'provider' => 'sakura',
    ]);

    $this->assertDatabaseHas('dns_zones', [
        'name' => 'local.override',
        'provider' => 'local',
    ]);

    expect(DnsZone::query()->count())->toBe(3);
});

it('does not duplicate default zones on repeated runs', function (): void {
    $this->seed(DnsZoneSeeder::class);
    $this->seed(DnsZoneSeeder::class);

    expect(DnsZone::query()->count())->toBe(3);
});
