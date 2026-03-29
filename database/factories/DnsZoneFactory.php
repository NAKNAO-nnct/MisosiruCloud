<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DnsZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DnsZone>
 */
class DnsZoneFactory extends Factory
{
    public function definition(): array
    {
        $zoneLabel = mb_strtolower((string) fake()->bothify('zone-####'));

        return [
            'name' => fake()->unique()->randomElement([
                $zoneLabel . '.example.com',
                $zoneLabel . '.infra.example.com',
            ]),
            'provider' => fake()->randomElement(['cloudflare', 'sakura', 'local']),
            'external_zone_id' => fake()->optional()->uuid(),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
