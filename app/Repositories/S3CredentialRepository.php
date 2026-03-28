<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\S3\S3CredentialData;
use App\Models\S3Credential;
use Illuminate\Pagination\LengthAwarePaginator;

class S3CredentialRepository
{
    public function findById(int $id): ?S3CredentialData
    {
        $model = S3Credential::query()->find($id);

        return $model ? S3CredentialData::of($model) : null;
    }

    public function findByIdOrFail(int $id): S3CredentialData
    {
        /** @var S3Credential $model */
        $model = S3Credential::query()->findOrFail($id);

        return S3CredentialData::of($model);
    }

    public function findActiveByTenantId(int $tenantId): ?S3CredentialData
    {
        $model = S3Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        return $model ? S3CredentialData::of($model) : null;
    }

    /**
     * @return LengthAwarePaginator<S3CredentialData>
     */
    public function paginateByTenantId(int $tenantId, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<S3CredentialData> $paginator */
        return S3Credential::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (S3Credential $c) => S3CredentialData::of($c));
    }

    public function countByTenantId(int $tenantId): int
    {
        return S3Credential::query()->where('tenant_id', $tenantId)->count();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): S3CredentialData
    {
        /** @var S3Credential $model */
        $model = S3Credential::create(array_merge(['tenant_id' => $tenantId], $data));

        return S3CredentialData::of($model);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): S3CredentialData
    {
        /** @var S3Credential $model */
        $model = S3Credential::query()->findOrFail($id);
        $model->update($data);

        return S3CredentialData::of($model->fresh());
    }
}
