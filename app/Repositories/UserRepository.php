<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\User\AuthUserData;
use App\Models\User;

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
     * Delete a user by ID.
     */
    public function delete(int $id): bool
    {
        return (bool) User::query()->where('id', $id)->delete();
    }
}
