<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class AuthUserData
{
    private function __construct(
        private int $id,
        private string $name,
        private string $email,
        private string $initials,
        private UserRole $role,
        private array $tenantNames,
    ) {
    }

    public static function of(User $model): self
    {
        return new self(
            id: (int) $model->id,
            name: $model->name,
            email: $model->email,
            initials: Str::of($model->name)
                ->explode(' ')
                ->take(2)
                ->map(fn (string $word): string => Str::substr($word, 0, 1))
                ->implode(''),
            role: $model->role,
            tenantNames: $model->relationLoaded('tenants')
                ? $model->tenants->pluck('name')->values()->all()
                : [],
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $role = $attributes['role'] ?? UserRole::TenantMember;

        return new self(
            id: (int) ($attributes['id'] ?? 0),
            name: (string) ($attributes['name'] ?? ''),
            email: (string) ($attributes['email'] ?? ''),
            initials: (string) ($attributes['initials'] ?? ''),
            role: $role instanceof UserRole ? $role : UserRole::from((string) $role),
            tenantNames: isset($attributes['tenant_names']) && is_array($attributes['tenant_names'])
                ? array_values(array_map(static fn (mixed $name): string => (string) $name, $attributes['tenant_names']))
                : [],
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getInitials(): string
    {
        return $this->initials;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    /**
     * @return array<int, string>
     */
    public function getTenantNames(): array
    {
        return $this->tenantNames;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'initials' => $this->initials,
            'role' => $this->role,
            'tenant_names' => $this->tenantNames,
        ];
    }
}
