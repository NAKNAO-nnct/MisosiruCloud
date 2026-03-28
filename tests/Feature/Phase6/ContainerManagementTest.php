<?php

declare(strict_types=1);

use App\Lib\Nomad\Client as NomadClient;
use App\Lib\Nomad\NomadApi;
use App\Models\ContainerJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('非ログインユーザはコンテナ一覧にアクセスできない', function (): void {
    $this->get(route('containers.index'))->assertRedirect('/login');
});

test('一般ユーザはコンテナ一覧にアクセスできない', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('containers.index'))
        ->assertForbidden();
});

test('管理者はコンテナ一覧にアクセスできる', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('containers.index'))
        ->assertSuccessful()
        ->assertSee('コンテナ一覧');
});

test('コンテナデプロイでNomad API呼び出しとTraefikタグ生成が行われる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create([
        'slug' => 'tenant-feature',
        'nomad_namespace' => 'tenant-feature',
    ]);

    config()->set('services.nomad.datacenter', 'dc1');

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/namespaces' => Http::response([
            ['Name' => 'default'],
        ], 200),
        'http://nomad.local:4646/v1/namespace' => Http::response(['Name' => 'tenant-feature'], 200),
        'http://nomad.local:4646/v1/jobs' => Http::response(['EvalID' => 'eval-feature'], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('containers.store'), [
            'tenant_id' => $tenant->id,
            'name' => 'web',
            'image' => 'nginx:stable',
            'domain' => 'feature.example.test',
            'replicas' => 2,
            'cpu_mhz' => 500,
            'memory_mb' => 256,
            'port_mappings' => [
                ['label' => 'http', 'to' => 80, 'value' => 8080],
            ],
            'env_vars' => [
                'APP_ENV' => 'production',
            ],
        ])
        ->assertRedirect(route('containers.index'));

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/v1/jobs')
        && str_contains($request->body(), 'traefik.enable=true')
        && str_contains($request->body(), 'Host(`feature.example.test`)'));
});

test('管理者はコンテナ詳細を表示できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-show', 'nomad_namespace' => 'tenant-show']);
    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-show-web',
    ]);

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-show-web/allocations' => Http::response([
            ['ID' => 'alloc-1', 'ClientStatus' => 'running'],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->get(route('containers.show', $job->id))
        ->assertSuccessful()
        ->assertSee('tenant-show-web');
});

test('一般ユーザは他テナントのコンテナ詳細にアクセスできない', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userB = User::factory()->create();
    $tenantB->users()->attach($userB);

    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenantA->id,
    ]);

    $this->actingAs($userB)
        ->get(route('containers.show', $job->id))
        ->assertForbidden();
});

test('コンテナのスケール変更ができる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-scale', 'nomad_namespace' => 'tenant-scale']);
    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-scale-api',
        'replicas' => 1,
    ]);

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-scale-api/scale' => Http::response(['EvalID' => 'eval-scale'], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('containers.scale', $job->id), ['replicas' => 3])
        ->assertRedirect(route('containers.show', $job->id));

    expect($job->fresh()->replicas)->toBe(3);
});

test('コンテナ再起動ができる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-restart', 'nomad_namespace' => 'tenant-restart']);
    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-restart-web',
    ]);

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-restart-web*' => Http::response(['EvalID' => 'eval-stop'], 200),
        'http://nomad.local:4646/v1/jobs' => Http::response(['EvalID' => 'eval-register'], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('containers.restart', $job->id))
        ->assertRedirect(route('containers.show', $job->id));
});

test('コンテナ削除ができる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-destroy', 'nomad_namespace' => 'tenant-destroy']);
    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-destroy-web',
    ]);

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-destroy-web*' => Http::response(['EvalID' => 'eval-stop'], 200),
    ]);

    $this->actingAs($admin)
        ->delete(route('containers.destroy', $job->id))
        ->assertRedirect(route('containers.index'));

    expect(ContainerJob::query()->whereKey($job->id)->exists())->toBeFalse();
});

test('コンテナログを取得できる', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-logs', 'nomad_namespace' => 'tenant-logs']);
    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-logs-web',
    ]);

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-logs-web/allocations' => Http::response([
            ['ID' => 'alloc-logs-1', 'ClientStatus' => 'running'],
        ], 200),
        'http://nomad.local:4646/v1/client/fs/logs/alloc-logs-1*' => Http::response("line-1\nline-2\n", 200),
    ]);

    $this->actingAs($admin)
        ->get(route('containers.logs', ['container' => $job->id, 'task_name' => 'app']))
        ->assertSuccessful()
        ->assertSee('line-1');
});

test('コンテナステータスAPIが応答する', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create(['slug' => 'tenant-status', 'nomad_namespace' => 'tenant-status']);
    $job = ContainerJob::factory()->create([
        'tenant_id' => $tenant->id,
        'nomad_job_id' => 'tenant-status-web',
    ]);

    $this->app->instance(
        NomadApi::class,
        new NomadApi(new NomadClient('http://nomad.local:4646', 'feature-token', false)),
    );

    Http::fake([
        'http://nomad.local:4646/v1/job/tenant-status-web/allocations' => Http::response([
            ['ID' => 'alloc-status-1', 'ClientStatus' => 'running'],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->get(route('api.containers.status', $job->id))
        ->assertSuccessful()
        ->assertJsonPath('status', 'running');
});
