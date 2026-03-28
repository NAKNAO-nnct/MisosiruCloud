<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenant\TenantData;
use App\Enums\TenantStatus;
use App\Lib\Proxmox\ProxmoxApi;
use App\Repositories\TenantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TenantService
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly S3CredentialService $s3CredentialService,
        private readonly ?ProxmoxApi $proxmoxApi,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function create(array $params): TenantData
    {
        return DB::transaction(function () use ($params): TenantData {
            $tenantData = $this->tenantRepository->create([
                'uuid' => Str::uuid()->toString(),
                'name' => $params['name'],
                'slug' => $params['slug'],
            ]);

            $id = $tenantData->getId();
            $vni = 10000 + $id;
            $vnetName = "tenant{$id}";
            $networkCidr = "10.{$id}.0.0/24";

            $tenantData = $this->tenantRepository->update($id, [
                'vni' => $vni,
                'vnet_name' => $vnetName,
                'network_cidr' => $networkCidr,
                'nomad_namespace' => $tenantData->getSlug(),
            ]);

            if ($this->proxmoxApi) {
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
                    Log::warning('SDN apply failed after tenant network creation', [
                        'tenant_id' => $tenantData->getId(),
                        'vnet' => $vnetName,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $this->s3CredentialService->createForTenant(
                tenant: $tenantData,
                bucket: 'dbaas-backups',
                prefix: $tenantData->getSlug() . '/',
                description: 'Default backup credential',
            );

            return $tenantData;
        });
    }

    /**
     * @param array<string, mixed> $params
     */
    public function update(int $id, array $params): TenantData
    {
        return $this->tenantRepository->update($id, $params);
    }

    public function delete(int $id): void
    {
        $tenantData = $this->tenantRepository->findByIdOrFail($id);

        if ($this->proxmoxApi && $tenantData->getVnetName()) {
            try {
                $this->proxmoxApi->cluster()->deleteVnet($tenantData->getVnetName());
                $this->proxmoxApi->cluster()->applySdn();
            } catch (Throwable) {
                // SDN削除失敗でもテナント状態変更は続行
            }
        }

        $this->tenantRepository->update($id, ['status' => TenantStatus::Deleted]);
    }

    private function resolveSdnZone(): string
    {
        if (!$this->proxmoxApi) {
            throw new RuntimeException('Proxmox API is not configured.');
        }

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
