<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DatabaseType;
use App\Models\DatabaseInstance;
use App\Models\Tenant;
use App\Models\VmMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatabaseInstance>
 */
class DatabaseInstanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'vm_meta_id' => VmMeta::factory()->for($tenant)->create()->id,
            'db_type' => fake()->randomElement(DatabaseType::cases()),
            'db_version' => fake()->randomElement(['8.4', '17', '7.2']),
            'port' => fake()->numberBetween(3306, 6379),
            'admin_user' => 'admin',
            'admin_password_encrypted' => fake()->password(16),
            'tenant_user' => null,
            'tenant_password_encrypted' => null,
            'backup_encryption_key_encrypted' => null,
            'status' => 'running',
        ];
    }
}
