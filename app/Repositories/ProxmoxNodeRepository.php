<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\ProxmoxNode\ProxmoxNodeData;
use App\Models\ProxmoxNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProxmoxNodeRepository
{
    public function findByIdOrFail(int $id): ProxmoxNodeData
    {
        /** @var ProxmoxNode $model */
        $model = ProxmoxNode::query()->findOrFail($id);

        return ProxmoxNodeData::of($model);
    }

    /**
     * @return Collection<int, ProxmoxNodeData>
     */
    public function all(): Collection
    {
        return ProxmoxNode::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (ProxmoxNode $n) => ProxmoxNodeData::of($n));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): ProxmoxNodeData
    {
        /** @var ProxmoxNode $model */
        $model = ProxmoxNode::create($data);

        return ProxmoxNodeData::of($model->fresh());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ProxmoxNodeData
    {
        /** @var ProxmoxNode $model */
        $model = ProxmoxNode::query()->findOrFail($id);
        $model->update($data);

        return ProxmoxNodeData::of($model->fresh());
    }

    public function delete(int $id): void
    {
        ProxmoxNode::query()->findOrFail($id)->delete();
    }

    /**
     * Activate the given node and deactivate all others atomically.
     */
    public function activate(int $id): void
    {
        DB::transaction(function () use ($id): void {
            ProxmoxNode::query()->where('id', '!=', $id)->update(['is_active' => false]);
            ProxmoxNode::query()->where('id', $id)->update(['is_active' => true]);
        });
    }

    /**
     * Deactivate the given node.
     */
    public function deactivate(int $id): void
    {
        ProxmoxNode::query()->where('id', $id)->update(['is_active' => false]);
    }
}
