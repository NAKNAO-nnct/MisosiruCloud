<?php

declare(strict_types=1);

namespace App\Data\Dbaas;

use App\Models\BackupSchedule;
use DateTimeImmutable;

final readonly class BackupScheduleData
{
    private function __construct(
        private int $id,
        private int $databaseInstanceId,
        private string $cronExpression,
        private int $retentionDaily,
        private int $retentionWeekly,
        private int $retentionMonthly,
        private ?DateTimeImmutable $lastBackupAt,
        private ?string $lastBackupStatus,
        private int $lastBackupSizeBytes,
        private bool $isEnabled,
    ) {
    }

    /**
     * Eloquent Model から生成 (Repository 内部でのみ使用).
     */
    public static function of(BackupSchedule $model): self
    {
        return new self(
            id: $model->id,
            databaseInstanceId: (int) $model->database_instance_id,
            cronExpression: (string) $model->cron_expression,
            retentionDaily: (int) ($model->retention_daily ?? 7),
            retentionWeekly: (int) ($model->retention_weekly ?? 4),
            retentionMonthly: (int) ($model->retention_monthly ?? 3),
            lastBackupAt: $model->last_backup_at
                ? DateTimeImmutable::createFromInterface($model->last_backup_at)
                : null,
            lastBackupStatus: $model->last_backup_status,
            lastBackupSizeBytes: (int) ($model->last_backup_size_bytes ?? 0),
            isEnabled: (bool) $model->is_enabled,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        return new self(
            id: (int) ($attributes['id'] ?? 0),
            databaseInstanceId: (int) ($attributes['database_instance_id'] ?? 0),
            cronExpression: (string) ($attributes['cron_expression'] ?? '0 3 * * *'),
            retentionDaily: (int) ($attributes['retention_daily'] ?? 7),
            retentionWeekly: (int) ($attributes['retention_weekly'] ?? 4),
            retentionMonthly: (int) ($attributes['retention_monthly'] ?? 3),
            lastBackupAt: null,
            lastBackupStatus: isset($attributes['last_backup_status']) ? (string) $attributes['last_backup_status'] : null,
            lastBackupSizeBytes: (int) ($attributes['last_backup_size_bytes'] ?? 0),
            isEnabled: (bool) ($attributes['is_enabled'] ?? true),
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDatabaseInstanceId(): int
    {
        return $this->databaseInstanceId;
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function getRetentionDaily(): int
    {
        return $this->retentionDaily;
    }

    public function getRetentionWeekly(): int
    {
        return $this->retentionWeekly;
    }

    public function getRetentionMonthly(): int
    {
        return $this->retentionMonthly;
    }

    public function getLastBackupAt(): ?DateTimeImmutable
    {
        return $this->lastBackupAt;
    }

    public function getLastBackupStatus(): ?string
    {
        return $this->lastBackupStatus;
    }

    public function getLastBackupSizeBytes(): int
    {
        return $this->lastBackupSizeBytes;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'database_instance_id' => $this->databaseInstanceId,
            'cron_expression' => $this->cronExpression,
            'retention_daily' => $this->retentionDaily,
            'retention_weekly' => $this->retentionWeekly,
            'retention_monthly' => $this->retentionMonthly,
            'last_backup_status' => $this->lastBackupStatus,
            'last_backup_size_bytes' => $this->lastBackupSizeBytes,
            'is_enabled' => $this->isEnabled,
        ];
    }
}
