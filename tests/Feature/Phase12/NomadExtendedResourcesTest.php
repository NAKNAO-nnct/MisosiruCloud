<?php

declare(strict_types=1);

use App\Data\Tenant\TenantData;
use App\Lib\Nomad\Client;
use App\Lib\Nomad\NomadApi;
use App\Repositories\ContainerJobRepository;
use App\Repositories\TenantRepository;
use App\Services\ContainerService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('allocation resource sends get allocation request', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/allocation/alloc-123' => Http::response([
            'ID' => 'alloc-123',
            'ClientStatus' => 'running',
        ], 200),
    ]);

    $api = new NomadApi(new Client('nomad.local', 'token'));

    $allocation = $api->allocation()->getAllocation('alloc-123');

    expect($allocation['ID'] ?? null)->toBe('alloc-123');
});

test('node resource maps nodes list response', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/nodes' => Http::response([
            ['ID' => 'node-1', 'Name' => 'worker-1'],
            ['ID' => 'node-2', 'Name' => 'worker-2'],
        ], 200),
    ]);

    $api = new NomadApi(new Client('nomad.local', 'token'));

    $nodes = $api->node()->listNodes();

    expect($nodes)->toHaveCount(2)
        ->and($nodes[0]['Name'] ?? null)->toBe('worker-1');
});

test('quota resource sends put request with quota spec', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/quota' => Http::response([
            'Index' => 10,
        ], 200),
    ]);

    $api = new NomadApi(new Client('nomad.local', 'token'));

    $result = $api->quota()->createQuota([
        'Name' => 'tenant-a',
        'Description' => 'Tenant quota for tenant-a',
    ]);

    expect($result['Index'] ?? null)->toBe(10);

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/v1/quota'));
});

test('container deploy creates namespace quota when namespace is missing', function (): void {
    Http::fake([
        'http://nomad.local:4646/v1/namespaces' => Http::response([
            ['Name' => 'default'],
        ], 200),
        'http://nomad.local:4646/v1/namespace' => Http::response(['Name' => 'tenant-x'], 200),
        'http://nomad.local:4646/v1/quota' => Http::response(['Index' => 1], 200),
        'http://nomad.local:4646/v1/jobs' => Http::response(['EvalID' => 'eval-1'], 200),
    ]);

    $nomadApi = new NomadApi(new Client('nomad.local', 'token'));

    $tenantRepository = Mockery::mock(TenantRepository::class);
    $containerJobRepository = Mockery::mock(ContainerJobRepository::class);

    $tenant = TenantData::make([
        'id' => 1,
        'uuid' => 'tenant-1',
        'name' => 'Tenant X',
        'slug' => 'tenant-x',
        'status' => 'active',
        'nomad_namespace' => 'tenant-x',
    ]);

    $containerJobRepository->shouldReceive('create')
        ->once()
        ->andReturn(App\Data\Container\ContainerJobData::make([
            'id' => 1,
            'tenant_id' => 1,
            'nomad_job_id' => 'tenant-x-web',
            'name' => 'web',
            'image' => 'nginx:latest',
            'replicas' => 1,
            'cpu_mhz' => 200,
            'memory_mb' => 256,
        ]));

    $service = new ContainerService($nomadApi, $containerJobRepository, $tenantRepository);

    $service->deployContainer($tenant, [
        'name' => 'web',
        'image' => 'nginx:latest',
        'replicas' => 1,
        'cpu_mhz' => 200,
        'memory_mb' => 256,
    ]);

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT' && str_contains($request->url(), '/v1/quota'));
});
