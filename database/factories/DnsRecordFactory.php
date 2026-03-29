<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DnsRecord;
use App\Models\DnsZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DnsRecord>
 */
class DnsRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dns_zone_id' => DnsZone::factory(),
            'name' => mb_strtolower((string) fake()->bothify('host-###')),
            'type' => fake()->randomElement(['A', 'AAAA', 'CNAME', 'TXT']),
            'content' => fake()->ipv4(),
            'ttl' => 300,
            'priority' => null,
            'external_id' => fake()->optional()->uuid(),
            'comment' => fake()->optional()->sentence(),
        ];
    }

    public function mx(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'MX',
            'content' => 'mail.example.com',
            'priority' => 10,
        ]);
    }
}
