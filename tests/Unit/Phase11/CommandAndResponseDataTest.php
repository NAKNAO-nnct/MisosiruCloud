<?php

declare(strict_types=1);

use App\Data\Container\DeployContainerCommand;
use App\Data\Dbaas\BackupScheduleData;
use App\Data\Dbaas\DatabaseInstanceData;
use App\Data\Dbaas\DbaasDetailResponseData;
use App\Data\Dbaas\ProvisionDbaasCommand;
use App\Data\Vm\ProvisionVmCommand;
use App\Data\Vm\VmDetailResponseData;
use App\Data\Vm\VmMetaData;

test('provision vm command maps attributes', function (): void {
    $command = ProvisionVmCommand::make([
        'tenant_id' => 10,
        'label' => 'web-01',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 310,
        'cpu' => 2,
        'memory_mb' => 2048,
        'disk_gb' => 20,
        'purpose' => 'app',
    ]);

    expect($command->getTenantId())->toBe(10)
        ->and($command->getNode())->toBe('pve1')
        ->and($command->toArray()['new_vmid'])->toBe(310);
});

test('provision dbaas command maps attributes', function (): void {
    $command = ProvisionDbaasCommand::make([
        'tenant_id' => 11,
        'db_type' => 'mysql',
        'db_version' => '8.4',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 701,
        'cpu' => 2,
        'memory_mb' => 4096,
    ]);

    expect($command->getTenantId())->toBe(11)
        ->and($command->getDbType()->value)->toBe('mysql')
        ->and($command->toArray()['memory_mb'])->toBe(4096);
});

test('deploy container command maps attributes', function (): void {
    $command = DeployContainerCommand::make([
        'tenant_id' => 12,
        'name' => 'api',
        'image' => 'nginx:latest',
        'replicas' => 2,
        'cpu_mhz' => 500,
        'memory_mb' => 1024,
        'port_mappings' => [['label' => 'http', 'to' => 80]],
        'env_vars' => ['APP_ENV' => 'prod'],
    ]);

    expect($command->getTenantId())->toBe(12)
        ->and($command->getName())->toBe('api')
        ->and($command->toArray()['replicas'])->toBe(2);
});

test('vm detail response data maps vm fields', function (): void {
    $response = VmDetailResponseData::make([
        'meta' => VmMetaData::make([
            'id' => 1,
            'tenant_id' => 1,
            'proxmox_vmid' => 310,
            'proxmox_node' => 'pve1',
            'label' => 'app-vm',
            'provisioning_status' => 'ready',
        ]),
        'status' => ['status' => 'running'],
        'node' => 'pve1',
    ]);

    expect($response->getMeta()?->getProxmoxVmid())->toBe(310)
        ->and($response->getStatus())->toBe(['status' => 'running'])
        ->and($response->getNode())->toBe('pve1');
});

test('dbaas detail response data maps fields', function (): void {
    $response = DbaasDetailResponseData::make([
        'database' => DatabaseInstanceData::make([
            'id' => 100,
            'tenant_id' => 1,
            'vm_meta_id' => 50,
            'db_type' => 'mysql',
            'db_version' => '8.4',
            'port' => 3306,
            'admin_user' => 'admin',
            'admin_password' => 'secret',
            'backup_encryption_key' => 'key',
            'status' => 'running',
        ]),
        'connection' => ['host' => '10.0.0.10'],
        'backups' => [['key' => 'backups/1/a.sql.gz']],
        'backupSchedule' => BackupScheduleData::make([
            'id' => 1,
            'database_instance_id' => 100,
            'cron_expression' => '0 3 * * *',
        ]),
        'tenantName' => 'tenant-a',
    ]);

    expect($response->getDatabase()->getId())->toBe(100)
        ->and($response->getConnection()['host'])->toBe('10.0.0.10')
        ->and($response->getBackups())->toHaveCount(1)
        ->and($response->getTenantName())->toBe('tenant-a');
});
