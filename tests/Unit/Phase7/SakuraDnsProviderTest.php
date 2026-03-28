<?php

declare(strict_types=1);

use App\Lib\Dns\SakuraDnsProvider;
use Illuminate\Support\Facades\Http;

test('sakura provider lists records', function (): void {
    Http::fake([
        'https://dns.local/v1/zones/example.com/records' => Http::response([
            'records' => [
                ['id' => '1', 'name' => 'app'],
            ],
        ], 200),
    ]);

    $provider = new SakuraDnsProvider('https://dns.local/v1', 'dns-token', 'example.com');
    $records = $provider->listRecords();

    expect($records)->toHaveCount(1)
        ->and($records[0]['name'])->toBe('app');
});

test('sakura provider create update delete record', function (): void {
    Http::fake([
        'https://dns.local/v1/zones/example.com/records' => Http::response(['record' => ['id' => '2']], 200),
        'https://dns.local/v1/zones/example.com/records/2' => Http::response(['record' => ['id' => '2']], 200),
    ]);

    $provider = new SakuraDnsProvider('https://dns.local/v1', 'dns-token', 'example.com');

    $provider->createRecord([
        'name' => 'db',
        'type' => 'A',
        'value' => '203.0.113.22',
        'ttl' => 300,
    ]);

    $provider->updateRecord('2', [
        'name' => 'db',
        'type' => 'A',
        'value' => '203.0.113.23',
        'ttl' => 300,
    ]);

    $provider->deleteRecord('2');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/records'));
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/records/2'));
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/records/2'));
});
