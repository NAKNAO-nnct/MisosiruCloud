<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Dbaas\DatabaseInstanceData;
use App\Data\Tenant\TenantData;
use App\Enums\DatabaseType;
use App\Lib\Proxmox\ProxmoxApi;
use App\Repositories\BackupScheduleRepository;
use App\Repositories\DatabaseInstanceRepository;
use App\Repositories\VmMetaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DbaasService
{
    public function __construct(
        private readonly VmService $vmService,
        private readonly ProxmoxApi $api,
        private readonly DatabaseInstanceRepository $dbInstanceRepository,
        private readonly BackupScheduleRepository $backupScheduleRepository,
        private readonly VmMetaRepository $vmMetaRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function provision(TenantData $tenant, array $params): DatabaseInstanceData
    {
        $dbType = DatabaseType::from($params['db_type']);
        $port = $this->defaultPort($dbType);
        $adminPassword = Str::random(32);
        $backupKey = Str::random(32);

        $vmParams = array_merge($params, [
            'label' => $params['label'] ?? "{$tenant->getSlug()}-{$params['db_type']}",
            'purpose' => $params['db_type'],
        ]);

        $vmMeta = $this->vmService->provisionVm($tenant, $vmParams);

        return DB::transaction(function () use ($tenant, $vmMeta, $dbType, $params, $port, $adminPassword, $backupKey): DatabaseInstanceData {
            $db = $this->dbInstanceRepository->create([
                'tenant_id' => $tenant->getId(),
                'vm_meta_id' => $vmMeta->getId(),
                'db_type' => $dbType,
                'db_version' => $params['db_version'],
                'port' => $port,
                'admin_user' => 'admin',
                'admin_password_encrypted' => $adminPassword,
                'backup_encryption_key_encrypted' => $backupKey,
                'status' => 'running',
            ]);

            $this->backupScheduleRepository->create([
                'database_instance_id' => $db->getId(),
                'cron_expression' => '0 3 * * *',
                'is_enabled' => true,
            ]);

            return $db;
        });
    }

    public function start(DatabaseInstanceData $db): void
    {
        $vmMeta = $this->vmMetaRepository->findByIdOrFail($db->getVmMetaId());
        $this->api->vm()->startVm($vmMeta->getProxmoxNode(), $vmMeta->getProxmoxVmid());
        $this->dbInstanceRepository->update($db->getId(), ['status' => 'running']);
    }

    public function stop(DatabaseInstanceData $db): void
    {
        $vmMeta = $this->vmMetaRepository->findByIdOrFail($db->getVmMetaId());
        $this->api->vm()->stopVm($vmMeta->getProxmoxNode(), $vmMeta->getProxmoxVmid());
        $this->dbInstanceRepository->update($db->getId(), ['status' => 'stopped']);
    }

    public function terminate(DatabaseInstanceData $db): void
    {
        $vmMeta = $this->vmMetaRepository->findByIdOrFail($db->getVmMetaId());
        $this->vmService->terminateVm($vmMeta);
        $this->dbInstanceRepository->delete($db->getId());
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnectionDetails(DatabaseInstanceData $db): array
    {
        $vmMeta = $this->vmMetaRepository->findByIdOrFail($db->getVmMetaId());

        return [
            'host' => $vmMeta->getSharedIpAddress() ?? $vmMeta->getProxmoxNode(),
            'port' => $db->getPort(),
            'db_type' => $db->getDbType()->value,
            'db_version' => $db->getDbVersion(),
            'admin_user' => $db->getAdminUser(),
            'admin_password' => $db->getAdminPassword(),
        ];
    }

    private function defaultPort(DatabaseType $type): int
    {
        return match ($type) {
            DatabaseType::Mysql => 3306,
            DatabaseType::Postgres => 5432,
            DatabaseType::Redis => 6379,
        };
    }
}
