<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Dns\DnsZoneData;
use App\Models\DnsZone;
use Illuminate\Support\Collection;

class DnsZoneRepository
{
    public function findByIdOrFail(int $id): DnsZoneData
    {
        /** @var DnsZone $model */
        $model = DnsZone::query()->findOrFail($id);

        return DnsZoneData::of($model);
    }

    public function findByProvider(string $provider): Collection
    {
        return DnsZone::query()
            ->where('provider', $provider)
            ->orderBy('name')
            ->get()
            ->map(fn (DnsZone $zone): DnsZoneData => DnsZoneData::of($zone));
    }

    public function all(): Collection
    {
        return DnsZone::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DnsZone $zone): DnsZoneData => DnsZoneData::of($zone));
    }

    public function create(array $data): DnsZoneData
    {
        /** @var DnsZone $model */
        $model = DnsZone::query()->create($data);

        return DnsZoneData::of($model->fresh());
    }

    public function update(int $id, array $data): DnsZoneData
    {
        /** @var DnsZone $model */
        $model = DnsZone::query()->findOrFail($id);
        $model->update($data);

        return DnsZoneData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        DnsZone::query()->findOrFail($id)->delete();
    }
}
