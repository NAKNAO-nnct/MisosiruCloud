<?php

declare(strict_types=1);

it('exposes phase 13 dns defaults', function (): void {
    expect(config('dns.providers.local.zones_path'))->toBe('/etc/coredns/zones')
        ->and(config('dns.providers.local.corefile_path'))->toBe('/etc/coredns/Corefile')
        ->and(config('dns.providers.local.container_name'))->toBe('dns')
        ->and(config('dns.providers.sakura.base_url'))->toBe('https://secure.sakura.ad.jp/cloud/zone/v1');
});

it('includes phase 13 infrastructure scaffold files', function (): void {
    expect(file_exists(base_path('compose.prod.yaml')))->toBeTrue()
        ->and(file_exists(base_path('.env.prod.example')))->toBeTrue()
        ->and(file_exists(base_path('docker/dns/Corefile')))->toBeTrue()
        ->and(file_exists(base_path('docker/dns/Dockerfile')))->toBeTrue()
        ->and(file_exists(base_path('docker/registry/harbor.yml')))->toBeTrue()
        ->and(file_exists(base_path('docker/otel/otel-collector-config.yaml')))->toBeTrue();
});

it('includes registry scaffold in production compose and env template', function (): void {
    $compose = file_get_contents(base_path('compose.prod.yaml')) ?: '';
    $envTemplate = file_get_contents(base_path('.env.prod.example')) ?: '';

    expect($compose)
        ->toContain('registry:')
        ->toContain('goharbor/harbor-core')
        ->toContain('./docker/registry/harbor.yml:/etc/harbor/harbor.yml:ro')
        ->toContain('${COMPOSE_REGISTRY_HTTPS_PORT:-443}:8443');

    expect($envTemplate)
        ->toContain('COMPOSE_REGISTRY_HTTPS_PORT=443')
        ->toContain('HARBOR_HOSTNAME=registry.infra.example.com')
        ->toContain('HARBOR_ADMIN_PASSWORD=change-me');
});
