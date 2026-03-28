<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'nomad_job_id', 'name', 'image', 'domain',
    'replicas', 'cpu_mhz', 'memory_mb', 'port_mappings', 'env_vars_encrypted',
])]
class ContainerJob extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'port_mappings' => 'array',
            'env_vars_encrypted' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
