<?php

declare(strict_types=1);

use App\Lib\Nomad\Client;
use App\Lib\Nomad\NomadApi;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('job resource can register and stop jobs with namespace and purge', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/jobs' => Http::response(['EvalID' => 'eval-1'], 200),
        'http://nomad.local:4646/v1/job/web-app*' => Http::response(['EvalID' => 'eval-2'], 200),
    ]);

    $api = new NomadApi(new Client('nomad.local', 'token'));

    $register = $api->job()->registerJob([
        'Job' => [
            'ID' => 'web-app',
            'Type' => 'service',
        ],
    ]);

    $stop = $api->job()->stopJob('web-app', 'tenant-a', true);

    expect($register['EvalID'] ?? null)->toBe('eval-1')
        ->and($stop['EvalID'] ?? null)->toBe('eval-2');

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/v1/job/web-app'));
});

test('job resource can fetch plain logs', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/client/fs/logs/alloc-1*' => Http::response('line-1\nline-2\n', 200),
    ]);

    $api = new NomadApi(new Client('nomad.local', 'token'));

    $logs = $api->job()->getJobLogs('alloc-1', 'web');

    expect($logs)->toContain('line-1')
        ->toContain('line-2');
});

test('namespace resource can list create and delete namespaces', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/namespaces' => Http::response([
            ['Name' => 'default'],
            ['Name' => 'tenant-a'],
        ], 200),
        'http://nomad.local:4646/v1/namespace' => Http::response(['Name' => 'tenant-a'], 200),
        'http://nomad.local:4646/v1/namespace/tenant-a' => Http::response([], 200),
    ]);

    $api = new NomadApi(new Client('nomad.local', 'token'));

    $namespaces = $api->namespace()->listNamespaces();
    $created = $api->namespace()->createNamespace('tenant-a', 'tenant namespace');
    $deleted = $api->namespace()->deleteNamespace('tenant-a');

    expect($namespaces)->toHaveCount(2)
        ->and($created['Name'] ?? null)->toBe('tenant-a')
        ->and($deleted)->toBeArray();
});
