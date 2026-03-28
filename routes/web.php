<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NodeStatusController as ApiNodeStatus;
use App\Http\Controllers\Api\VmStatusController as ApiVmStatus;
use App\Http\Controllers\Dashboard\IndexController as DashboardIndex;
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
    });

    Route::prefix('api')->name('api.')->group(function (): void {
        Route::get('vms/{vmid}/status', ApiVmStatus::class)->name('vms.status');
        Route::get('nodes/status', ApiNodeStatus::class)->name('nodes.status');
    });
});

