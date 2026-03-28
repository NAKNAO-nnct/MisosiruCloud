<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'slug', 'status', 'vnet_name', 'vni', 'network_cidr', 'nomad_namespace', 'metadata'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'metadata' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('role');
    }

    public function vmMetas(): HasMany
    {
        return $this->hasMany(VmMeta::class);
    }

    public function databaseInstances(): HasMany
    {
        return $this->hasMany(DatabaseInstance::class);
    }

    public function containerJobs(): HasMany
    {
        return $this->hasMany(ContainerJob::class);
    }

    public function s3Credentials(): HasMany
    {
        return $this->hasMany(S3Credential::class);
    }
}
