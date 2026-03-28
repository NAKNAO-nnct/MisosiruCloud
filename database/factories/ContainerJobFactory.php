<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContainerJob;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerJob>
 */
class ContainerJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nomad_job_id' => fake()->unique()->slug(2),
            'name' => fake()->word(),
            'image' => fake()->randomElement(['nginx:stable', 'redis:7', 'mysql:8']),
            'domain' => fake()->domainName(),
            'replicas' => fake()->numberBetween(1, 3),
            'cpu_mhz' => fake()->randomElement([200, 500, 1000]),
            'memory_mb' => fake()->randomElement([128, 256, 512]),
            'port_mappings' => [
                ['label' => 'http', 'to' => 80, 'value' => 8080],
            ],
            'env_vars_encrypted' => 'APP_ENV=production',
        ];
    }
}
