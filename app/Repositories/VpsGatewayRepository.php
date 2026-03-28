<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\VpsGateway\VpsGatewayData;
use App\Models\VpsGateway;
use Illuminate\Support\Collection;

class VpsGatewayRepository
{
    public function findByIdOrFail(int $id): VpsGatewayData
    {
        /** @var VpsGateway $model */
        $model = VpsGateway::query()->findOrFail($id);

        return VpsGatewayData::of($model);
    }

    /**
     * @return Collection<int, VpsGatewayData>
     */
    public function all(): Collection
    {
        return VpsGateway::query()
            ->orderBy('name')
            ->get()
            ->map(fn (VpsGateway $gateway) => VpsGatewayData::of($gateway));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): VpsGatewayData
    {
        /** @var VpsGateway $model */
        $model = VpsGateway::query()->create($data);

        return VpsGatewayData::of($model->fresh());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): VpsGatewayData
    {
        /** @var VpsGateway $model */
        $model = VpsGateway::query()->findOrFail($id);
        $model->update($data);

        return VpsGatewayData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        VpsGateway::query()->findOrFail($id)->delete();
    }

    public function nextSequence(): int
    {
        return ((int) VpsGateway::query()->max('id')) + 1;
    }
}
