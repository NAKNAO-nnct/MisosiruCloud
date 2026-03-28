<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Tenant\TenantData;
use App\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TenantRepository
{
    public function findById(int $id): ?TenantData
    {
        $model = Tenant::query()->find($id);

        return $model ? TenantData::of($model) : null;
    }

    public function findByIdOrFail(int $id): TenantData
    {
        /** @var Tenant $model */
        $model = Tenant::query()->findOrFail($id);

        return TenantData::of($model);
    }

    /**
     * @return LengthAwarePaginator<TenantData>
     */
    public function paginate(?string $search, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<TenantData> $paginator */
        return Tenant::query()
            ->when(
                $search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%"),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (Tenant $t) => TenantData::of($t));
    }

    /**
     * @return Collection<int, TenantData>
     */
    public function all(): Collection
    {
        return Tenant::query()
            ->get()
            ->map(fn (Tenant $t) => TenantData::of($t));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): TenantData
    {
        /** @var Tenant $model */
        $model = Tenant::create($data);

        return TenantData::of($model->fresh());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): TenantData
    {
        /** @var Tenant $model */
        $model = Tenant::query()->findOrFail($id);
        $model->update($data);

        return TenantData::of($model->fresh());
    }
}
