<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\User\AuthUserData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UserRepository
{
    /**
     * Find user by ID.
     */
    public function findById(int $id): ?AuthUserData
    {
        $user = User::query()->find($id);

        return $user ? AuthUserData::of($user) : null;
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?AuthUserData
    {
        $user = User::query()->where('email', $email)->first();

        return $user ? AuthUserData::of($user) : null;
    }

    /**
     * Get all users.
     *
     * @return array<int, AuthUserData>
     */
    public function all(): array
    {
        return User::query()
            ->get()
            ->map(fn (User $user) => AuthUserData::of($user))
            ->toArray();
    }

    /**
     * @return array<int, AuthUserData>
     */
    public function allWithTenants(): array
    {
        return User::query()
            ->with('tenants')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => AuthUserData::of($user))
            ->toArray();
    }

    /**
     * Create a new user.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): AuthUserData
    {
        $user = User::query()->create($attributes);

        return AuthUserData::of($user);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, int>      $tenantIds
     */
    public function createWithTenants(array $attributes, array $tenantIds): AuthUserData
    {
        /** @var User $user */
        $user = DB::transaction(function () use ($attributes, $tenantIds): User {
            /** @var User $created */
            $created = User::query()->create($attributes);
            $created->tenants()->sync($this->buildTenantSyncPayload($tenantIds, $created->role));

            return $created->fresh(['tenants']);
        });

        return AuthUserData::of($user);
    }

    /**
     * Update an existing user.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(int $id, array $attributes): AuthUserData
    {
        $user = User::query()->findOrFail($id);
        $user->update($attributes);

        return AuthUserData::of($user);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, int>      $tenantIds
     */
    public function updateWithTenants(int $id, array $attributes, array $tenantIds): AuthUserData
    {
        /** @var User $user */
        $user = DB::transaction(function () use ($id, $attributes, $tenantIds): User {
            /** @var User $model */
            $model = User::query()->findOrFail($id);
            $model->update($attributes);
            $model->tenants()->sync($this->buildTenantSyncPayload($tenantIds, $model->role));

            return $model->fresh(['tenants']);
        });

        return AuthUserData::of($user);
    }

    /**
     * Delete a user by ID.
     */
    public function delete(int $id): bool
    {
        return (bool) User::query()->where('id', $id)->delete();
    }

    /**
     * @param array<int, int> $tenantIds
     *
     * @return array<int, array{role: string}>
     */
    private function buildTenantSyncPayload(array $tenantIds, UserRole $role): array
    {
        $tenantRole = $role === UserRole::TenantAdmin ? 'admin' : 'member';
        $syncPayload = [];

        foreach ($tenantIds as $tenantId) {
            $syncPayload[$tenantId] = ['role' => $tenantRole];
        }

        return $syncPayload;
    }
}
