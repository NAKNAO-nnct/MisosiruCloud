<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenant\TenantData;
use App\Enums\TenantStatus;
use App\Lib\Proxmox\ProxmoxApi;
use App\Repositories\TenantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            $vnetName = "tenant-{$id}";
            $networkCidr = "10.{$id}.0.0/24";

            $tenantData = $this->tenantRepository->update($id, [
                'vni' => $vni,
                'vnet_name' => $vnetName,
                'network_cidr' => $networkCidr,
                'nomad_namespace' => $tenantData->getSlug(),
            ]);

            if ($this->proxmoxApi) {
                $this->proxmoxApi->cluster()->createVnet([
                    'vnet' => $vnetName,
                    'zone' => 'localzone',
                    'tag' => $vni,
                ]);

                $this->proxmoxApi->cluster()->createSubnet($vnetName, [
                    'subnet' => $networkCidr,
                    'type' => 'subnet',
                ]);

                $this->proxmoxApi->cluster()->applySdn();
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
}
