<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'database_instance_id', 'cron_expression',
    'retention_daily', 'retention_weekly', 'retention_monthly',
    'last_backup_at', 'last_backup_status', 'last_backup_size_bytes', 'is_enabled',
])]
class BackupSchedule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_backup_at' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    public function databaseInstance(): BelongsTo
    {
        return $this->belongsTo(DatabaseInstance::class);
    }
}
