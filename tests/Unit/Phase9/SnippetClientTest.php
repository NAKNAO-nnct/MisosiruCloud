<?php

declare(strict_types=1);

use App\Lib\Snippet\Exceptions\SnippetApiException;
use App\Lib\Snippet\SnippetClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('snippet client upload sends vm id endpoint and bearer header', function (): void {
    Http::fake([
        'http://snippet.local/snippets/100' => Http::response(['ok' => true], 200),
    ]);

    $client = new SnippetClient('http://snippet.local', 'secret-token');
    $client->upload(100, "#cloud-config\nhostname: vm-100\n", "version: 2\n", "instance-id: vm-100\n");

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'http://snippet.local/snippets/100'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && str_contains($request->body(), 'user_data')
        && str_contains($request->body(), 'network_config')
        && str_contains($request->body(), 'meta_data'));
});

test('snippet client get fetches vm id endpoint', function (): void {
    Http::fake([
        'http://snippet.local/snippets/101' => Http::response([
            'vm_id' => '101',
            'files' => ['user_data' => '/var/lib/vz/snippets/101-user.yaml'],
            'created_at' => '2026-03-29T00:00:00Z',
            'updated_at' => '2026-03-29T00:00:00Z',
        ], 200),
    ]);

    $client = new SnippetClient('http://snippet.local', 'secret-token');
    $result = $client->get(101);

    expect($result['vm_id'] ?? null)->toBe('101');
});

test('snippet client delete sends vm id endpoint', function (): void {
    Http::fake([
        'http://snippet.local/snippets/102' => Http::response(['status' => 'deleted'], 200),
    ]);

    $client = new SnippetClient('http://snippet.local', 'secret-token');
    $client->delete(102);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && $request->url() === 'http://snippet.local/snippets/102');
});

test('snippet client rejects invalid vm id', function (): void {
    $client = new SnippetClient('http://snippet.local', 'secret-token');

    expect(fn () => $client->upload(99, 'x'))
        ->toThrow(SnippetApiException::class, 'Invalid vm_id.');

    expect(fn () => $client->delete(1_000_000_000))
        ->toThrow(SnippetApiException::class, 'Invalid vm_id.');
});
