<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set('services.dns.sakura.base_url', 'https://dns.local/v1');
    config()->set('services.dns.sakura.api_token', 'dns-token');
    config()->set('services.dns.sakura.zone', 'example.com');
});

test('一般ユーザは DNS 管理にアクセスできない', function (): void {
    $user = User::factory()->create(['role' => UserRole::TenantMember]);

    $this->actingAs($user)
        ->get(route('dns.index'))
        ->assertForbidden();
});

test('管理者は DNS レコード一覧を表示できる', function (): void {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'https://dns.local/v1/zones/example.com/records' => Http::response([
            'records' => [
                ['id' => '1', 'name' => 'app', 'type' => 'A', 'value' => '203.0.113.10', 'ttl' => 300],
            ],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->get(route('dns.index'))
        ->assertSuccessful()
        ->assertSee('DNS 管理')
        ->assertSee('app')
        ->assertSee('203.0.113.10');
});

test('管理者は DNS レコードを追加できる', function (): void {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'https://dns.local/v1/zones/example.com/records' => Http::response([
            'record' => ['id' => '2'],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('dns.store'), [
            'name' => 'db',
            'type' => 'A',
            'value' => '203.0.113.20',
            'ttl' => 300,
        ])
        ->assertRedirect(route('dns.index'));

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/zones/example.com/records')
        && str_contains($request->body(), '203.0.113.20'));
});

test('管理者は DNS レコードを更新できる', function (): void {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'https://dns.local/v1/zones/example.com/records/10' => Http::response([
            'record' => ['id' => '10'],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->put(route('dns.update', '10'), [
            'name' => 'app',
            'type' => 'A',
            'value' => '203.0.113.99',
            'ttl' => 600,
        ])
        ->assertRedirect(route('dns.index'));

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/records/10')
        && str_contains($request->body(), '203.0.113.99'));
});

test('管理者は DNS レコードを削除できる', function (): void {
    $admin = User::factory()->admin()->create();

    Http::fake([
        'https://dns.local/v1/zones/example.com/records/10' => Http::response([], 200),
    ]);

    $this->actingAs($admin)
        ->delete(route('dns.destroy', '10'))
        ->assertRedirect(route('dns.index'));

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), '/records/10'));
});
