<?php

declare(strict_types=1);

namespace App\Lib\Dns;

use RuntimeException;

class DnsProviderFactory
{
    /**
     * @param array<string, mixed>|null $providersConfig
     */
    public function __construct(private readonly ?array $providersConfig = null)
    {
    }

    public function make(string $provider, string $zoneName, ?string $externalZoneId = null): DnsProviderInterface
    {
        $providers = $this->providersConfig ?? (array) config('dns.providers', []);

        return match ($provider) {
            'cloudflare' => new CloudflareDnsProvider(
                apiToken: (string) ($providers['cloudflare']['api_token'] ?? ''),
                zoneId: $externalZoneId ?? $zoneName,
            ),
            'sakura' => new SakuraDnsProvider(
                baseUrl: (string) ($providers['sakura']['base_url'] ?? ''),
                apiToken: (string) ($providers['sakura']['api_token'] ?? ''),
                zone: $externalZoneId ?? $zoneName,
            ),
            'local' => new LocalDnsProvider(
                zonesPath: (string) ($providers['local']['zones_path'] ?? '/etc/coredns/zones'),
                corefilePath: (string) ($providers['local']['corefile_path'] ?? '/etc/coredns/Corefile'),
                containerName: (string) ($providers['local']['container_name'] ?? 'dns'),
            ),
            default => throw new RuntimeException('Unsupported DNS provider: ' . $provider),
        };
    }
}
