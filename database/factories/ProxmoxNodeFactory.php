<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProxmoxNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProxmoxNode>
 */
class ProxmoxNodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'pve' . fake()->unique()->numberBetween(1, 99),
            'hostname' => fake()->ipv4() . ':8006',
            'api_token_id' => 'root@pam!token-' . fake()->word(),
            'api_token_secret_encrypted' => fake()->uuid(),
            'snippet_api_url' => 'http://' . fake()->ipv4() . ':8080',
            'snippet_api_token_encrypted' => fake()->sha256(),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}

