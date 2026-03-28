<?php

declare(strict_types=1);

use App\Lib\Nomad\Client as NomadClient;
use App\Lib\Nomad\NomadApi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

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
