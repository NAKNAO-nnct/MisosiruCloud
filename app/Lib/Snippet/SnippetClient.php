<?php

declare(strict_types=1);

namespace App\Lib\Snippet;

use App\Lib\Snippet\Exceptions\SnippetApiException;
use Illuminate\Support\Facades\Http;

class SnippetClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {
    }

    public function upload(string $filename, string $content): void
    {
        $this->validateFilename($filename);

        $response = $this->request()->post($this->url('/snippets'), [
            'filename' => $filename,
            'content' => $content,
        ]);

        if ($response->failed()) {
            throw new SnippetApiException('Snippet upload failed: ' . $response->status() . ' ' . $response->body());
        }
    }

    public function delete(string $filename): void
    {
        $this->validateFilename($filename);

        $response = $this->request()->delete($this->url('/snippets/' . $filename));

        if ($response->failed()) {
            throw new SnippetApiException('Snippet delete failed: ' . $response->status() . ' ' . $response->body());
        }
    }

    private function validateFilename(string $filename): void
    {
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new SnippetApiException('Invalid snippet filename.');
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->asJson()->withToken($this->token);
    }

    private function url(string $path): string
    {
        return mb_rtrim($this->baseUrl, '/') . '/' . mb_ltrim($path, '/');
    }
}
