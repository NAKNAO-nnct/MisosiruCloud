<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VpsGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpsGateway>
 */
class VpsGatewayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = fake()->unique()->numberBetween(1, 200);

        return [
            'name' => 'vps-gw-' . $seq,
            'global_ip' => fake()->ipv4(),
            'wireguard_ip' => sprintf('10.255.%d.1', $seq),
            'wireguard_port' => 51820,
            'wireguard_public_key' => str_repeat('a', 43) . '=',
            'transit_wireguard_port' => 51820 + $seq,
            'status' => 'active',
            'purpose' => fake()->word(),
            'metadata' => null,
        ];
    }
}
