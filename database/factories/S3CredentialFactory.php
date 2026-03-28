<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\S3Credential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<S3Credential>
 */
class S3CredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'access_key' => 'MSIR' . mb_strtoupper(Str::random(16)),
            'secret_key_encrypted' => Str::random(40),
            'allowed_bucket' => 'dbaas-backups',
            'allowed_prefix' => fake()->slug() . '/',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
