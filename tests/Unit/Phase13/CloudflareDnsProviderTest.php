<?php

declare(strict_types=1);

use App\Lib\Dns\CloudflareDnsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('cloudflare provider lists records', function (): void {
    Http::fake([
        'https://api.cloudflare.com/client/v4/zones/zone-1/dns_records' => Http::response([
            'result' => [
                ['id' => '1', 'name' => 'app.example.com'],
            ],
        ], 200),
    ]);

    $provider = new CloudflareDnsProvider('cf-token', 'zone-1');

    $records = $provider->listRecords();

    expect($records)->toHaveCount(1)
        ->and($records[0]['name'])->toBe('app.example.com');
});

test('cloudflare provider create update delete record', function (): void {
    Http::fake([
        'https://api.cloudflare.com/client/v4/zones/zone-1/dns_records' => Http::response([
            'result' => ['id' => '10'],
        ], 200),
        'https://api.cloudflare.com/client/v4/zones/zone-1/dns_records/10' => Http::response([
            'result' => ['id' => '10'],
        ], 200),
    ]);

    $provider = new CloudflareDnsProvider('cf-token', 'zone-1');

    $provider->createRecord([
        'name' => 'db.example.com',
        'type' => 'A',
        'content' => '203.0.113.10',
        'ttl' => 300,
    ]);

    $provider->updateRecord('10', [
        'name' => 'db.example.com',
        'type' => 'A',
        'content' => '203.0.113.11',
        'ttl' => 300,
    ]);

    $provider->deleteRecord('10');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST' && str_contains($request->url(), '/dns_records'));
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT' && str_contains($request->url(), '/dns_records/10'));
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/dns_records/10'));
});
