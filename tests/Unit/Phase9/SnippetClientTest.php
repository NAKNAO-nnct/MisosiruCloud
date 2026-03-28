<?php

declare(strict_types=1);

use App\Lib\Snippet\Exceptions\SnippetApiException;
use App\Lib\Snippet\SnippetClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('snippet client upload sends correct endpoint and bearer header', function (): void {
    Http::fake([
        'http://snippet.local/snippets' => Http::response(['ok' => true], 200),
    ]);

    $client = new SnippetClient('http://snippet.local', 'secret-token');
    $client->upload('vm-100-user-data.yaml', "#cloud-config\nhostname: vm-100\n");

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'http://snippet.local/snippets'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && str_contains($request->body(), 'vm-100-user-data.yaml'));
});

test('snippet client rejects path traversal filename', function (): void {
    $client = new SnippetClient('http://snippet.local', 'secret-token');

    expect(fn () => $client->upload('../etc/passwd', 'x'))
        ->toThrow(SnippetApiException::class);
});
