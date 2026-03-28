<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VmStatus;
use App\Models\Tenant;
use App\Models\VmMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VmMeta>
 */
class VmMetaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'proxmox_vmid' => fake()->unique()->numberBetween(100, 9999),
            'proxmox_node' => fake()->randomElement(['pve1', 'pve2', 'pve3']),
            'purpose' => fake()->randomElement(['general', 'dbaas', 'nomad_worker']),
            'label' => fake()->words(2, true),
            'shared_ip_address' => null,
            'provisioning_status' => VmStatus::Ready,
            'provisioning_error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'provisioning_status' => VmStatus::Pending,
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'provisioning_status' => VmStatus::Error,
            'provisioning_error' => fake()->sentence(),
        ]);
    }
}
