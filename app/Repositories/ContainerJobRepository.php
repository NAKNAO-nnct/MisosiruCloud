<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Container\ContainerJobData;
use App\Models\ContainerJob;
use Illuminate\Pagination\LengthAwarePaginator;

class ContainerJobRepository
{
    public function findById(int $id): ?ContainerJobData
    {
        $model = ContainerJob::query()->find($id);

        return $model ? ContainerJobData::of($model) : null;
    }

    public function findByIdOrFail(int $id): ContainerJobData
    {
        /** @var ContainerJob $model */
        $model = ContainerJob::query()->findOrFail($id);

        return ContainerJobData::of($model);
    }

    /**
     * @return LengthAwarePaginator<ContainerJobData>
     */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<ContainerJobData> $paginator */
        return ContainerJob::query()
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (ContainerJob $job) => ContainerJobData::of($job));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ContainerJobData
    {
        /** @var ContainerJob $model */
        $model = ContainerJob::query()->create($data);

        return ContainerJobData::of($model->fresh());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ContainerJobData
    {
        /** @var ContainerJob $model */
        $model = ContainerJob::query()->findOrFail($id);
        $model->update($data);

        return ContainerJobData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        ContainerJob::query()->findOrFail($id)->delete();
    }
}
