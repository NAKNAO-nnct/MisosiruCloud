<?php

declare(strict_types=1);

use App\Lib\Nomad\Client;
use App\Lib\Nomad\Exceptions\NomadApiException;
use App\Lib\Nomad\Exceptions\NomadAuthException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('client sends bearer token to nomad api', function (): void {
    Http::fake([
        'http://nomad.example.local:4646/v1/jobs' => Http::response([
            ['ID' => 'job-1'],
        ], 200),
    ]);

    $client = new Client('nomad.example.local', 'token-123');
    $client->get('jobs');

    Http::assertSent(fn ($request): bool => $request->header('Authorization')[0] === 'Bearer token-123');
});

test('client uses explicit scheme and port when provided', function (): void {
    Http::fake([
        'https://nomad.example.local:5646/v1/jobs' => Http::response([], 200),
    ]);

    $client = new Client('https://nomad.example.local:5646', 'token-123');
    $client->get('jobs');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://nomad.example.local:5646/v1/jobs');
});

test('client throws auth exception on 403', function (): void {
    Http::fake([
        '*' => Http::response([], 403),
    ]);

    $client = new Client('nomad.example.local', 'invalid-token');

    expect(fn () => $client->get('jobs'))->toThrow(NomadAuthException::class);
});

test('client throws api exception on 500', function (): void {
    Http::fake([
        '*' => Http::response(['error' => 'internal'], 500),
    ]);

    $client = new Client('nomad.example.local', 'token-123');

    expect(fn () => $client->get('jobs'))->toThrow(NomadApiException::class);
});
