<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Dbaas\DatabaseInstanceData;
use App\Models\DatabaseInstance;

class DatabaseInstanceRepository
{
    public function findById(int $id): ?DatabaseInstanceData
    {
        $model = DatabaseInstance::query()->find($id);

        return $model ? DatabaseInstanceData::of($model) : null;
    }

    public function findByIdOrFail(int $id): DatabaseInstanceData
    {
        /** @var DatabaseInstance $model */
        $model = DatabaseInstance::query()->findOrFail($id);

        return DatabaseInstanceData::of($model);
    }

    public function countByTenantId(int $tenantId): int
    {
        return DatabaseInstance::query()->where('tenant_id', $tenantId)->count();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): DatabaseInstanceData
    {
        /** @var DatabaseInstance $model */
        $model = DatabaseInstance::create($data);

        return DatabaseInstanceData::of($model);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): DatabaseInstanceData
    {
        /** @var DatabaseInstance $model */
        $model = DatabaseInstance::query()->findOrFail($id);
        $model->update($data);

        return DatabaseInstanceData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        DatabaseInstance::query()->findOrFail($id)->delete();
    }
}
