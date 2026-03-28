<?php

declare(strict_types=1);

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
    });
});

