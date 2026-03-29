<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Vm\VmMetaData;
use App\Models\VmMeta;
use Illuminate\Support\Collection;

class VmMetaRepository
{
    public function findById(int $id): ?VmMetaData
    {
        $model = VmMeta::query()->find($id);

        return $model ? VmMetaData::of($model) : null;
    }

    public function findByIdOrFail(int $id): VmMetaData
    {
        /** @var VmMeta $model */
        $model = VmMeta::query()->findOrFail($id);

        return VmMetaData::of($model);
    }

    public function findByVmid(int $vmid): ?VmMetaData
    {
        $model = VmMeta::query()->where('proxmox_vmid', $vmid)->first();

        return $model ? VmMetaData::of($model) : null;
    }

    public function findByVmidOrFail(int $vmid): VmMetaData
    {
        /** @var VmMeta $model */
        $model = VmMeta::query()->where('proxmox_vmid', $vmid)->firstOrFail();

        return VmMetaData::of($model);
    }

    public function findByVmidWithTenant(int $vmid): ?VmMetaData
    {
        $model = VmMeta::query()
            ->where('proxmox_vmid', $vmid)
            ->with('tenant')
            ->first();

        return $model ? VmMetaData::of($model) : null;
    }

    /**
     * @return Collection<int, VmMetaData>
     */
    public function allWithTenant(): Collection
    {
        return VmMeta::query()
            ->with('tenant')
            ->get()
            ->keyBy('proxmox_vmid')
            ->map(fn (VmMeta $m) => VmMetaData::of($m));
    }

    public function countByTenantId(int $tenantId): int
    {
        return VmMeta::query()->where('tenant_id', $tenantId)->count();
    }

    /**
     * @return Collection<int, VmMetaData>
     */
    public function findByTenantId(int $tenantId): Collection
    {
        return VmMeta::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('proxmox_vmid')
            ->get()
            ->map(fn (VmMeta $vm) => VmMetaData::of($vm));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): VmMetaData
    {
        /** @var VmMeta $model */
        $model = VmMeta::create($data);

        return VmMetaData::of($model);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): VmMetaData
    {
        /** @var VmMeta $model */
        $model = VmMeta::query()->findOrFail($id);
        $model->update($data);

        return VmMetaData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        VmMeta::query()->findOrFail($id)->delete();
    }

    public function forceDelete(int $id): void
    {
        VmMeta::query()->withTrashed()->findOrFail($id)->forceDelete();
    }

    /**
     * @return Collection<int, VmMetaData>
     */
    public function allWithTenantDesc(): Collection
    {
        return VmMeta::query()
            ->with('tenant')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (VmMeta $m) => VmMetaData::of($m));
    }
}
