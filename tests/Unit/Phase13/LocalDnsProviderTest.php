<?php

declare(strict_types=1);

use App\Lib\Dns\LocalDnsProvider;
use Illuminate\Support\Collection;

test('local dns provider regenerates a zone file', function (): void {
    $basePath = sys_get_temp_dir() . '/misosiru-dns-' . uniqid();
    $zonesPath = $basePath . '/zones';
    $corefilePath = $basePath . '/Corefile';

    $provider = new LocalDnsProvider(
        zonesPath: $zonesPath,
        corefilePath: $corefilePath,
        containerName: 'dns',
    );

    $zonePath = $provider->regenerateZoneFile('local.override', new Collection([
        ['name' => '@', 'type' => 'A', 'content' => '172.26.26.10', 'ttl' => 300],
        ['name' => 'registry', 'type' => 'A', 'content' => '172.26.26.10', 'ttl' => 300],
        ['name' => 'mail', 'type' => 'MX', 'content' => 'mail.local.override.', 'ttl' => 300, 'priority' => 10],
    ]));

    expect(is_file($zonePath))->toBeTrue();

    $content = file_get_contents($zonePath) ?: '';

    expect($content)
        ->toContain('SOA ns1.local.override.')
        ->toContain('@ 300 IN A 172.26.26.10')
        ->toContain('registry 300 IN A 172.26.26.10')
        ->toContain('mail 300 IN MX 10 mail.local.override.');
});

test('local dns provider regenerates corefile for local zones', function (): void {
    $basePath = sys_get_temp_dir() . '/misosiru-dns-' . uniqid();
    $zonesPath = $basePath . '/zones';
    $corefilePath = $basePath . '/Corefile';

    $provider = new LocalDnsProvider(
        zonesPath: $zonesPath,
        corefilePath: $corefilePath,
        containerName: 'dns',
    );

    $provider->regenerateCorefile(new Collection(['local.override', 'example.local']));

    expect(is_file($corefilePath))->toBeTrue();

    $content = file_get_contents($corefilePath) ?: '';

    expect($content)
        ->toContain('local.override:53 {')
        ->toContain('file ' . $zonesPath . '/db.local.override')
        ->toContain('example.local:53 {')
        ->toContain('.:53 {')
        ->toContain('forward . 8.8.8.8 8.8.4.4');
});
