<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContainerStatusController as ApiContainerStatus;
use App\Http\Controllers\Api\DbaasStatusController as ApiDbaasStatus;
use App\Http\Controllers\Api\NodeStatusController as ApiNodeStatus;
use App\Http\Controllers\Api\VmStatusController as ApiVmStatus;
use App\Http\Controllers\Container\CreateController as ContainerCreate;
use App\Http\Controllers\Container\DestroyController as ContainerDestroy;
use App\Http\Controllers\Container\IndexController as ContainerIndex;
use App\Http\Controllers\Container\LogsController as ContainerLogs;
use App\Http\Controllers\Container\RestartController as ContainerRestart;
use App\Http\Controllers\Container\ScaleController as ContainerScale;
use App\Http\Controllers\Container\ShowController as ContainerShow;
use App\Http\Controllers\Container\StoreController as ContainerStore;
use App\Http\Controllers\Dashboard\IndexController as DashboardIndex;
use App\Http\Controllers\Dbaas\BackupController as DbaasBackup;
use App\Http\Controllers\Dbaas\BackupsController as DbaasBackups;
use App\Http\Controllers\Dbaas\CreateController as DbaasCreate;
use App\Http\Controllers\Dbaas\CredentialsController as DbaasCredentials;
use App\Http\Controllers\Dbaas\DestroyController as DbaasDestroy;
use App\Http\Controllers\Dbaas\IndexController as DbaasIndex;
use App\Http\Controllers\Dbaas\RestoreController as DbaasRestore;
use App\Http\Controllers\Dbaas\ShowController as DbaasShow;
use App\Http\Controllers\Dbaas\StartController as DbaasStart;
use App\Http\Controllers\Dbaas\StopController as DbaasStop;
use App\Http\Controllers\Dbaas\StoreController as DbaasStore;
use App\Http\Controllers\Dbaas\UpgradeController as DbaasUpgrade;
use App\Http\Controllers\ProxmoxNode\ActivateController as ProxmoxNodeActivate;
use App\Http\Controllers\ProxmoxNode\CreateController as ProxmoxNodeCreate;
use App\Http\Controllers\ProxmoxNode\DeactivateController as ProxmoxNodeDeactivate;
use App\Http\Controllers\ProxmoxNode\DestroyController as ProxmoxNodeDestroy;
use App\Http\Controllers\ProxmoxNode\EditController as ProxmoxNodeEdit;
use App\Http\Controllers\ProxmoxNode\IndexController as ProxmoxNodeIndex;
use App\Http\Controllers\ProxmoxNode\StoreController as ProxmoxNodeStore;
use App\Http\Controllers\ProxmoxNode\UpdateController as ProxmoxNodeUpdate;
use App\Http\Controllers\S3Credential\DestroyController as S3CredentialDestroy;
use App\Http\Controllers\S3Credential\IndexController as S3CredentialIndex;
use App\Http\Controllers\S3Credential\RotateController as S3CredentialRotate;
use App\Http\Controllers\S3Credential\ShowController as S3CredentialShow;
use App\Http\Controllers\S3Credential\StoreController as S3CredentialStore;
use App\Http\Controllers\Tenant\CreateController as TenantCreate;
use App\Http\Controllers\Tenant\DestroyController as TenantDestroy;
use App\Http\Controllers\Tenant\EditController as TenantEdit;
use App\Http\Controllers\Tenant\IndexController as TenantIndex;
use App\Http\Controllers\Tenant\ShowController as TenantShow;
use App\Http\Controllers\Tenant\StoreController as TenantStore;
use App\Http\Controllers\Tenant\UpdateController as TenantUpdate;
use App\Http\Controllers\Vm\ConsoleController as VmConsole;
use App\Http\Controllers\Vm\CreateController as VmCreate;
use App\Http\Controllers\Vm\DestroyController as VmDestroy;
use App\Http\Controllers\Vm\ForceStopController as VmForceStop;
use App\Http\Controllers\Vm\IndexController as VmIndex;
use App\Http\Controllers\Vm\RebootController as VmReboot;
use App\Http\Controllers\Vm\ResizeController as VmResize;
use App\Http\Controllers\Vm\ShowController as VmShow;
use App\Http\Controllers\Vm\SnapshotController as VmSnapshot;
use App\Http\Controllers\Vm\StartController as VmStart;
use App\Http\Controllers\Vm\StopController as VmStop;
use App\Http\Controllers\Vm\StoreController as VmStore;
use App\Http\Controllers\VpsGateway\DestroyController as VpsGatewayDestroy;
use App\Http\Controllers\VpsGateway\IndexController as VpsGatewayIndex;
use App\Http\Controllers\VpsGateway\ShowController as VpsGatewayShow;
use App\Http\Controllers\VpsGateway\StoreController as VpsGatewayStore;
use App\Http\Controllers\VpsGateway\SyncController as VpsGatewaySync;
use App\Http\Controllers\VpsGateway\UpdateController as VpsGatewayUpdate;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardIndex::class)->name('dashboard');

    Route::middleware('admin')->prefix('admin')->group(function (): void {
        Route::prefix('tenants')->name('tenants.')->group(function (): void {
            Route::get('/', TenantIndex::class)->name('index');
            Route::get('/create', TenantCreate::class)->name('create');
            Route::post('/', TenantStore::class)->name('store');
            Route::get('/{tenant}', TenantShow::class)->name('show');
            Route::get('/{tenant}/edit', TenantEdit::class)->name('edit');
            Route::put('/{tenant}', TenantUpdate::class)->name('update');
            Route::delete('/{tenant}', TenantDestroy::class)->name('destroy');

            Route::prefix('/{tenant}/s3-credentials')->name('s3-credentials.')->group(function (): void {
                Route::get('/', S3CredentialIndex::class)->name('index');
                Route::post('/', S3CredentialStore::class)->name('store');
                Route::get('/{s3Credential}', S3CredentialShow::class)->name('show');
                Route::delete('/{s3Credential}', S3CredentialDestroy::class)->name('destroy');
                Route::put('/{s3Credential}/rotate', S3CredentialRotate::class)->name('rotate');
            });
        });

        Route::prefix('vms')->name('vms.')->group(function (): void {
            Route::get('/', VmIndex::class)->name('index');
            Route::get('/create', VmCreate::class)->name('create');
            Route::post('/', VmStore::class)->name('store');
            Route::get('/{vmid}', VmShow::class)->name('show');
            Route::get('/{vmid}/console', VmConsole::class)->name('console');
            Route::post('/{vmid}/start', VmStart::class)->name('start');
            Route::post('/{vmid}/stop', VmStop::class)->name('stop');
            Route::post('/{vmid}/reboot', VmReboot::class)->name('reboot');
            Route::post('/{vmid}/force-stop', VmForceStop::class)->name('force-stop');
            Route::post('/{vmid}/snapshot', VmSnapshot::class)->name('snapshot');
            Route::post('/{vmid}/resize', VmResize::class)->name('resize');
            Route::delete('/{vmid}', VmDestroy::class)->name('destroy');
        });

        Route::prefix('dbaas')->name('dbaas.')->group(function (): void {
            Route::get('/', DbaasIndex::class)->name('index');
            Route::get('/create', DbaasCreate::class)->name('create');
            Route::post('/', DbaasStore::class)->name('store');
            Route::get('/{database}', DbaasShow::class)->name('show');
            Route::post('/{database}/start', DbaasStart::class)->name('start');
            Route::post('/{database}/stop', DbaasStop::class)->name('stop');
            Route::delete('/{database}', DbaasDestroy::class)->name('destroy');
            Route::post('/{database}/backup', DbaasBackup::class)->name('backup');
            Route::get('/{database}/backups', DbaasBackups::class)->name('backups');
            Route::post('/{database}/restore', DbaasRestore::class)->name('restore');
            Route::get('/{database}/credentials', DbaasCredentials::class)->name('credentials');
            Route::post('/{database}/upgrade', DbaasUpgrade::class)->name('upgrade');
        });

        Route::prefix('containers')->name('containers.')->group(function (): void {
            Route::get('/', ContainerIndex::class)->name('index');
            Route::get('/deploy', ContainerCreate::class)->name('create');
            Route::post('/', ContainerStore::class)->name('store');
            Route::get('/{container}', ContainerShow::class)->name('show');
            Route::post('/{container}/restart', ContainerRestart::class)->name('restart');
            Route::post('/{container}/scale', ContainerScale::class)->name('scale');
            Route::delete('/{container}', ContainerDestroy::class)->name('destroy');
            Route::get('/{container}/logs', ContainerLogs::class)->name('logs');
        });

        Route::prefix('proxmox-clusters')->name('proxmox-clusters.')->group(function (): void {
            Route::get('/', ProxmoxNodeIndex::class)->name('index');
            Route::get('/create', ProxmoxNodeCreate::class)->name('create');
            Route::post('/', ProxmoxNodeStore::class)->name('store');
            Route::get('/{proxmoxNode}/edit', ProxmoxNodeEdit::class)->name('edit');
            Route::put('/{proxmoxNode}', ProxmoxNodeUpdate::class)->name('update');
            Route::delete('/{proxmoxNode}', ProxmoxNodeDestroy::class)->name('destroy');
            Route::post('/{proxmoxNode}/activate', ProxmoxNodeActivate::class)->name('activate');
            Route::post('/{proxmoxNode}/deactivate', ProxmoxNodeDeactivate::class)->name('deactivate');
        });

        Route::prefix('vps-gateways')->name('vps-gateways.')->group(function (): void {
            Route::get('/', VpsGatewayIndex::class)->name('index');
            Route::post('/', VpsGatewayStore::class)->name('store');
            Route::get('/{vpsGateway}', VpsGatewayShow::class)->name('show');
            Route::put('/{vpsGateway}', VpsGatewayUpdate::class)->name('update');
            Route::delete('/{vpsGateway}', VpsGatewayDestroy::class)->name('destroy');
            Route::post('/{vpsGateway}/sync', VpsGatewaySync::class)->name('sync');
        });
    });

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::get('vms/{vmid}/status', ApiVmStatus::class)->name('vms.status');
        Route::get('nodes/status', ApiNodeStatus::class)->name('nodes.status');
        Route::get('dbaas/{database}/status', ApiDbaasStatus::class)->name('dbaas.status');
        Route::get('containers/{container}/status', ApiContainerStatus::class)->name('containers.status');
    });
});

