<?php

declare(strict_types=1);

use App\Lib\Nomad\Client as NomadClient;
use App\Lib\Nomad\NomadApi;
use App\Models\ContainerJob;
use App\Models\Tenant;
use App\Services\ContainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set('services.nomad.datacenter', 'dc1');

    app()->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'test-token', false)),
    );
});

test('deployContainer creates job record and registers nomad job with traefik tags', function (): void {
    $tenant = Tenant::factory()->create([
        'slug' => 'tenant-a',
        'nomad_namespace' => 'tenant-a',
    ]);

    Http::fake([
        'http://nomad.local:4646/v1/namespaces' => Http::response([
            ['Name' => 'default'],
        ], 200),
        'http://nomad.local:4646/v1/namespace' => Http::response(['Name' => 'tenant-a'], 200),
        'http://nomad.local:4646/v1/jobs' => Http::response(['EvalID' => 'eval-1'], 200),
    ]);

    $service = app()->make(ContainerService::class);

    $job = $service->deployContainer(
        tenant: App\Data\Tenant\TenantData::of($tenant),
        params: [
            'name' => 'web',
            'image' => 'nginx:stable',
            'domain' => 'web.example.test',
            'replicas' => 2,
            'cpu_mhz' => 500,
            'memory_mb' => 256,
            'port_mappings' => [
                ['label' => 'http', 'to' => 80, 'value' => 8080],
            ],
            'env_vars' => [
                'APP_ENV' => 'production',
            ],
        ],
    );

    expect($job->getTenantId())->toBe($tenant->id)
        ->and($job->getReplicas())->toBe(2)
        ->and(ContainerJob::query()->where('nomad_job_id', $job->getNomadJobId())->exists())->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/v1/jobs')
        && str_contains($request->body(), 'traefik.enable=true')
        && str_contains($request->body(), 'Host(`web.example.test`)'));
});

test('scaleContainer updates replicas and sends scale api call', function (): void {
    $tenant = Tenant::factory()->create([
        'slug' => 'tenant-b',
        'nomad_namespace' => 'tenant-b',
    ]);

    $containerJob = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-b-api',
        'replicas' => 1,
    ]);

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-b-api/scale' => Http::response(['EvalID' => 'eval-scale'], 200),
    ]);

    $service = app()->make(ContainerService::class);
    $service->scaleContainer(App\Data\Container\ContainerJobData::of($containerJob), 3);

    expect($containerJob->fresh()->replicas)->toBe(3);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/v1/job/tenant-b-api/scale'));
});

test('terminateContainer purges nomad job and deletes container job record', function (): void {
    $tenant = Tenant::factory()->create([
        'slug' => 'tenant-c',
        'nomad_namespace' => 'tenant-c',
    ]);

    $containerJob = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-c-worker',
    ]);

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-c-worker*' => Http::response(['EvalID' => 'eval-stop'], 200),
    ]);

    $service = app()->make(ContainerService::class);
    $service->terminateContainer(App\Data\Container\ContainerJobData::of($containerJob));

    expect(ContainerJob::query()->whereKey($containerJob->id)->exists())->toBeFalse();

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/v1/job/tenant-c-worker'));
});

test('deployContainer stores env vars encrypted at rest', function (): void {
    $tenant = Tenant::factory()->create([
        'slug' => 'tenant-env',
        'nomad_namespace' => 'tenant-env',
    ]);

    Http::fake([
        'http://nomad.local:4646/v1/namespaces' => Http::response([
            ['Name' => 'default'],
        ], 200),
        'http://nomad.local:4646/v1/namespace' => Http::response(['Name' => 'tenant-env'], 200),
        'http://nomad.local:4646/v1/jobs' => Http::response(['EvalID' => 'eval-env'], 200),
    ]);

    $service = app()->make(ContainerService::class);

    $job = $service->deployContainer(
        tenant: App\Data\Tenant\TenantData::of($tenant),
        params: [
            'name' => 'api',
            'image' => 'nginx:stable',
            'replicas' => 1,
            'cpu_mhz' => 300,
            'memory_mb' => 256,
            'env_vars' => [
                'APP_KEY' => 'secret-value',
                'APP_ENV' => 'production',
            ],
        ],
    );

    $stored = ContainerJob::query()->findOrFail($job->getId());
    $decrypted = json_encode([
        'APP_KEY' => 'secret-value',
        'APP_ENV' => 'production',
    ], JSON_UNESCAPED_SLASHES);

    expect($stored->env_vars_encrypted)->toBe($decrypted);

    $rawCiphertext = DB::table('container_jobs')
        ->where('id', $stored->id)
        ->value('env_vars_encrypted');

    expect($rawCiphertext)
        ->toBeString()
        ->not->toBe($decrypted);
});
