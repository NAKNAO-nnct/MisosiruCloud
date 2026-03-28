<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = fake()->unique()->numberBetween(1, 9999);
        $slug = fake()->unique()->slug(2);

        return [
            'uuid' => Str::uuid()->toString(),
            'name' => fake()->company(),
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'vnet_name' => 'tenant-' . $id,
            'vni' => 10000 + $id,
            'network_cidr' => '10.' . $id . '.0.0/24',
            'nomad_namespace' => 'tenant-' . $id,
            'metadata' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenantStatus::Suspended,
        ]);
    }
}
