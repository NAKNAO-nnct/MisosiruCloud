<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\VpsGateway\VpsGatewayData;
use App\Repositories\VpsGatewayRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VpsGatewayService
{
    public function __construct(private readonly VpsGatewayRepository $vpsGatewayRepository)
    {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function register(array $params): VpsGatewayData
    {
        return DB::transaction(function () use ($params): VpsGatewayData {
            $sequence = $this->vpsGatewayRepository->nextSequence();
            $wireguardIp = $this->buildWireguardIp($sequence);
            $transitWireguardPort = 51820 + $sequence;

            return $this->vpsGatewayRepository->create([
                'name' => (string) $params['name'],
                'global_ip' => (string) $params['global_ip'],
                'wireguard_ip' => $wireguardIp,
                'wireguard_port' => isset($params['wireguard_port']) ? (int) $params['wireguard_port'] : 51820,
                'wireguard_public_key' => (string) $params['wireguard_public_key'],
                'transit_wireguard_port' => $transitWireguardPort,
                'status' => isset($params['status']) ? (string) $params['status'] : 'active',
                'purpose' => isset($params['purpose']) ? (string) $params['purpose'] : null,
                'metadata' => null,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(VpsGatewayData $gateway): array
    {
        $result = [
            'gateway_id' => $gateway->getId(),
            'status' => $gateway->getStatus(),
            'last_synced_at' => now()->toIso8601String(),
            'reachable' => in_array($gateway->getStatus(), ['active', 'maintenance'], true),
        ];

        $metadata = $gateway->getMetadata() ?? [];
        $metadata['sync'] = $result;

        $this->vpsGatewayRepository->update($gateway->getId(), [
            'metadata' => $metadata,
        ]);

        return $result;
    }

    public function generateWireguardConfig(VpsGatewayData $gateway): string
    {
        $transitAddress = $this->buildTransitAddress($gateway->getWireguardIp());

        return implode("\n", [
            '# Transit VM -> VPS Gateway (' . $gateway->getName() . ')',
            '[Interface]',
            'Address = ' . $transitAddress . '/32',
            'PrivateKey = <TRANSIT_VM_PRIVATE_KEY>',
            'ListenPort = ' . $gateway->getTransitWireguardPort(),
            '',
            '[Peer]',
            'PublicKey = ' . $gateway->getWireguardPublicKey(),
            'Endpoint = ' . $gateway->getGlobalIp() . ':' . $gateway->getWireguardPort(),
            'AllowedIPs = ' . $gateway->getWireguardIp() . '/32',
            'PersistentKeepalive = 25',
            '',
        ]);
    }

    public function destroy(VpsGatewayData $gateway): void
    {
        $this->vpsGatewayRepository->delete($gateway->getId());
    }

    private function buildWireguardIp(int $sequence): string
    {
        if ($sequence < 1 || $sequence > 254) {
            throw new RuntimeException('No available WireGuard IP address slot.');
        }

        return sprintf('10.255.%d.1', $sequence);
    }

    private function buildTransitAddress(string $wireguardIp): string
    {
        $parts = explode('.', $wireguardIp);

        if (count($parts) !== 4) {
            return '10.255.0.254';
        }

        return sprintf('%s.%s.%s.254', $parts[0], $parts[1], $parts[2]);
    }
}
