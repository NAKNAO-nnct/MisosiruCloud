<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VmStatus;
use Database\Factories\VmMetaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'proxmox_vmid', 'proxmox_node', 'purpose', 'label', 'shared_ip_address', 'ip_address', 'gateway', 'vnet_name', 'ssh_keys', 'provisioning_status', 'provisioning_error'])]
class VmMeta extends Model
{
    /** @use HasFactory<VmMetaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'provisioning_status' => VmStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function databaseInstance(): HasOne
    {
        return $this->hasOne(DatabaseInstance::class);
    }
}
