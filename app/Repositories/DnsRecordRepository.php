<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Dns\DnsRecordData;
use App\Models\DnsRecord;
use Illuminate\Support\Collection;

class DnsRecordRepository
{
    public function findByIdOrFail(int $id): DnsRecordData
    {
        /** @var DnsRecord $record */
        $record = DnsRecord::query()->findOrFail($id);

        return DnsRecordData::of($record);
    }

    public function findByZoneId(int $zoneId): Collection
    {
        return DnsRecord::query()
            ->where('dns_zone_id', $zoneId)
            ->orderBy('name')
            ->orderBy('type')
            ->get()
            ->map(fn (DnsRecord $record): DnsRecordData => DnsRecordData::of($record));
    }

    public function findByZoneIdAndType(int $zoneId, string $type): Collection
    {
        return DnsRecord::query()
            ->where('dns_zone_id', $zoneId)
            ->where('type', $type)
            ->orderBy('name')
            ->get()
            ->map(fn (DnsRecord $record): DnsRecordData => DnsRecordData::of($record));
    }

    public function create(array $data): DnsRecordData
    {
        /** @var DnsRecord $model */
        $model = DnsRecord::query()->create($data);

        return DnsRecordData::of($model->fresh());
    }

    public function update(int $id, array $data): DnsRecordData
    {
        /** @var DnsRecord $model */
        $model = DnsRecord::query()->findOrFail($id);
        $model->update($data);

        return DnsRecordData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        DnsRecord::query()->findOrFail($id)->delete();
    }
}
