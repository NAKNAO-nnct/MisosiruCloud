<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DnsRecord;
use App\Models\DnsZone;
use Illuminate\Database\Seeder;
use RuntimeException;

class DnsRecordSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DnsZoneSeeder::class);

        $zones = DnsZone::query()
            ->whereIn('name', ['example.com', 'infra.example.com', 'local.override'])
            ->get()
            ->keyBy('name');

        $globalZone = $zones->get('example.com');
        $infraZone = $zones->get('infra.example.com');
        $localOverrideZone = $zones->get('local.override');

        if (!$globalZone || !$infraZone || !$localOverrideZone) {
            throw new RuntimeException('Required DNS zones are missing for DnsRecordSeeder.');
        }

        $records = [
            // example.com (Cloudflare)
            [
                'dns_zone_id' => $globalZone->id,
                'name' => '@',
                'type' => 'A',
                'content' => '198.51.100.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'example.com -> VPS global IP',
            ],
            [
                'dns_zone_id' => $globalZone->id,
                'name' => '*.containers',
                'type' => 'A',
                'content' => '198.51.100.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => '*.containers.example.com -> VPS global IP',
            ],
            [
                'dns_zone_id' => $globalZone->id,
                'name' => 'infra',
                'type' => 'NS',
                'content' => 'ns1.sakura.example.',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'infra.example.com delegated to sakura DNS',
            ],

            // infra.example.com (sakura)
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'mgmt',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Management app',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 's3',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'S3 proxy',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'registry',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Container registry',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'dns',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'CoreDNS endpoint',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'otel',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'OTel collector',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'snippet-pve1',
                'type' => 'A',
                'content' => '172.26.26.11',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Snippet API sidecar on pve1',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'snippet-pve2',
                'type' => 'A',
                'content' => '172.26.26.12',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Snippet API sidecar on pve2',
            ],
            [
                'dns_zone_id' => $infraZone->id,
                'name' => 'snippet-pve3',
                'type' => 'A',
                'content' => '172.26.26.13',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Snippet API sidecar on pve3',
            ],

            // local.override (CoreDNS split-horizon)
            [
                'dns_zone_id' => $localOverrideZone->id,
                'name' => 'registry.example.com',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Local override for registry',
            ],
            [
                'dns_zone_id' => $localOverrideZone->id,
                'name' => 'mgmt.example.com',
                'type' => 'A',
                'content' => '172.26.26.10',
                'ttl' => 300,
                'priority' => null,
                'comment' => 'Local override for management app',
            ],
        ];

        foreach ($records as $record) {
            DnsRecord::query()->updateOrCreate(
                [
                    'dns_zone_id' => $record['dns_zone_id'],
                    'name' => $record['name'],
                    'type' => $record['type'],
                ],
                $record,
            );
        }
    }
}
