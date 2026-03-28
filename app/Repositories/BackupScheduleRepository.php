<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Dbaas\BackupScheduleData;
use App\Models\BackupSchedule;
use Illuminate\Support\Collection;

class BackupScheduleRepository
{
    public function findByDatabaseInstanceId(int $dbInstanceId): ?BackupScheduleData
    {
        $model = BackupSchedule::query()
            ->where('database_instance_id', $dbInstanceId)
            ->first();

        return $model ? BackupScheduleData::of($model) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): BackupScheduleData
    {
        /** @var BackupSchedule $model */
        $model = BackupSchedule::create($data);

        return BackupScheduleData::of($model);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): BackupScheduleData
    {
        /** @var BackupSchedule $model */
        $model = BackupSchedule::query()->findOrFail($id);
        $model->update($data);

        return BackupScheduleData::of($model->fresh());
    }

    /**
     * @return Collection<int, BackupScheduleData>
     */
    public function allEnabled(): Collection
    {
        return BackupSchedule::query()
            ->where('is_enabled', true)
            ->orderBy('database_instance_id')
            ->get()
            ->map(fn (BackupSchedule $schedule) => BackupScheduleData::of($schedule));
    }
}
