<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatabaseType;
use Database\Factories\DatabaseInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id', 'vm_meta_id', 'db_type', 'db_version', 'port',
    'admin_user', 'admin_password_encrypted',
    'tenant_user', 'tenant_password_encrypted',
    'backup_encryption_key_encrypted', 'status',
])]
class DatabaseInstance extends Model
{
    /** @use HasFactory<DatabaseInstanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'db_type' => DatabaseType::class,
            'admin_password_encrypted' => 'encrypted',
            'tenant_password_encrypted' => 'encrypted',
            'backup_encryption_key_encrypted' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vmMeta(): BelongsTo
    {
        return $this->belongsTo(VmMeta::class);
    }

    public function backupSchedule(): HasOne
    {
        return $this->hasOne(BackupSchedule::class);
    }
}
