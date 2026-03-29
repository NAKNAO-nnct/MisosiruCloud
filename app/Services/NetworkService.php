<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Network\NetworkData;
use App\Data\Tenant\TenantData;
use App\Lib\Proxmox\ProxmoxApi;
use App\Repositories\TenantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class NetworkService
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly ?ProxmoxApi $proxmoxApi,
    ) {
    }

    /**
     * @return array<int, NetworkData>
     */
    public function listNetworks(): array
    {
        $tenants = $this->tenantRepository->all();
        $vnets = [];

        if ($this->proxmoxApi) {
            foreach ($this->proxmoxApi->cluster()->listVnets() as $vnet) {
                $name = $vnet['vnet'] ?? null;

                if (is_string($name) && $name !== '') {
                    $vnets[$name] = $vnet;
                }
            }
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            $vnetName = $tenant->getVnetName();
            $matched = $vnetName ? ($vnets[$vnetName] ?? null) : null;

            $rows[] = NetworkData::make([
                'tenant_id' => $tenant->getId(),
                'tenant_name' => $tenant->getName(),
                'tenant_slug' => $tenant->getSlug(),
                'vnet_name' => $vnetName,
                'vni' => $tenant->getVni(),
                'network_cidr' => $tenant->getNetworkCidr(),
                'proxmox_zone' => is_array($matched) ? ($matched['zone'] ?? null) : null,
                'exists_on_proxmox' => is_array($matched),
            ]);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function createNetwork(int $tenantId, array $params): TenantData
    {
        $this->ensureProxmoxConfigured();

        return DB::transaction(function () use ($tenantId, $params): TenantData {
            $tenant = $this->tenantRepository->findByIdOrFail($tenantId);

            $vnetName = $tenant->getVnetName() ?: sprintf('tenant%d', $tenant->getId());
            $vni = $tenant->getVni() ?: (10000 + $tenant->getId());
            $networkCidr = (string) $params['network_cidr'];
            $zone = $this->resolveSdnZone();

            $this->proxmoxApi->cluster()->createVnet([
                'vnet' => $vnetName,
                'zone' => $zone,
                'tag' => $vni,
            ]);

            $this->proxmoxApi->cluster()->createSubnet($vnetName, [
                'subnet' => $networkCidr,
                'type' => 'subnet',
            ]);

            try {
                $this->proxmoxApi->cluster()->applySdn();
            } catch (Throwable $e) {
                Log::warning('SDN apply failed after network creation', [
                    'tenant_id' => $tenant->getId(),
                    'vnet' => $vnetName,
                    'message' => $e->getMessage(),
                ]);
            }

            return $this->tenantRepository->update($tenant->getId(), [
                'vnet_name' => $vnetName,
                'vni' => $vni,
                'network_cidr' => $networkCidr,
            ]);
        });
    }

    public function deleteNetwork(int $tenantId): TenantData
    {
        $this->ensureProxmoxConfigured();

        return DB::transaction(function () use ($tenantId): TenantData {
            $tenant = $this->tenantRepository->findByIdOrFail($tenantId);
            $vnetName = $tenant->getVnetName();

            if ($vnetName) {
                $this->proxmoxApi->cluster()->deleteVnet($vnetName);

                try {
                    $this->proxmoxApi->cluster()->applySdn();
                } catch (Throwable $e) {
                    Log::warning('SDN apply failed after network deletion', [
                        'tenant_id' => $tenant->getId(),
                        'vnet' => $vnetName,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $this->tenantRepository->update($tenant->getId(), [
                'vnet_name' => null,
                'vni' => null,
                'network_cidr' => null,
            ]);
        });
    }

    private function ensureProxmoxConfigured(): void
    {
        if (!$this->proxmoxApi) {
            throw new RuntimeException('Proxmox API is not configured.');
        }
    }

    private function resolveSdnZone(): string
    {
        $zones = $this->proxmoxApi->cluster()->listZones();
        $preferredZone = (string) config('services.proxmox.sdn_zone', '');

        if ($preferredZone !== '') {
            foreach ($zones as $zone) {
                if (($zone['zone'] ?? null) === $preferredZone) {
                    return $preferredZone;
                }
            }
        }

        foreach ($zones as $zone) {
            if (($zone['zone'] ?? null) === 'localzone') {
                return 'localzone';
            }
        }

        foreach ($zones as $zone) {
            $zoneName = $zone['zone'] ?? null;

            if (is_string($zoneName) && $zoneName !== '') {
                return $zoneName;
            }
        }

        throw new RuntimeException('No available Proxmox SDN zone found.');
    }
}
