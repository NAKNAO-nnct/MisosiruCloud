<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DnsZone;
use Illuminate\Database\Seeder;

class DnsZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'example.com',
                'provider' => 'cloudflare',
                'description' => 'Public zone for services and wildcard records.',
                'is_active' => true,
            ],
            [
                'name' => 'infra.example.com',
                'provider' => 'sakura',
                'description' => 'Internal infrastructure zone.',
                'is_active' => true,
            ],
            [
                'name' => 'local.override',
                'provider' => 'local',
                'description' => 'Split-horizon local override zone for CoreDNS.',
                'is_active' => true,
            ],
        ];

        foreach ($zones as $zone) {
            DnsZone::query()->updateOrCreate(
                ['name' => $zone['name']],
                $zone,
            );
        }
    }
}
