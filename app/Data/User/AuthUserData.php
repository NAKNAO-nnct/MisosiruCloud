<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class AuthUserData
{
    private function __construct(
        private string $name,
        private string $initials,
        private UserRole $role,
    ) {
    }

    public static function of(User $model): self
    {
        return new self(
            name: $model->name,
            initials: Str::of($model->name)
                ->explode(' ')
                ->take(2)
                ->map(fn (string $word): string => Str::substr($word, 0, 1))
                ->implode(''),
            role: $model->role,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $role = $attributes['role'] ?? UserRole::TenantMember;

        return new self(
            name: (string) ($attributes['name'] ?? ''),
            initials: (string) ($attributes['initials'] ?? ''),
            role: $role instanceof UserRole ? $role : UserRole::from((string) $role),
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInitials(): string
    {
        return $this->initials;
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
            'name' => $this->name,
            'initials' => $this->initials,
            'role' => $this->role,
        ];
    }
}
