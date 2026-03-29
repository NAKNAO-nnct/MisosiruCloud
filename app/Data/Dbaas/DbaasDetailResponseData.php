<?php

declare(strict_types=1);

namespace App\Data\Dbaas;

final readonly class DbaasDetailResponseData
{
    /**
     * @param array<string, mixed>             $connection
     * @param array<int, array<string, mixed>> $backups
     */
    private function __construct(
        private DatabaseInstanceData $database,
        private array $connection,
        private array $backups,
        private ?BackupScheduleData $backupSchedule,
        private ?string $tenantName,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $database = $attributes['database'] ?? null;
        $backupSchedule = $attributes['backupSchedule'] ?? null;

        return new self(
            database: $database instanceof DatabaseInstanceData
                ? $database
                : DatabaseInstanceData::make(is_array($database) ? $database : []),
            connection: isset($attributes['connection']) && is_array($attributes['connection'])
                ? $attributes['connection']
                : [],
            backups: isset($attributes['backups']) && is_array($attributes['backups'])
                ? $attributes['backups']
                : [],
            backupSchedule: $backupSchedule instanceof BackupScheduleData
                ? $backupSchedule
                : (is_array($backupSchedule) ? BackupScheduleData::make($backupSchedule) : null),
            tenantName: isset($attributes['tenantName']) ? (string) $attributes['tenantName'] : null,
        );
    }

    public function getDatabase(): DatabaseInstanceData
    {
        return $this->database;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConnection(): array
    {
        return $this->connection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBackups(): array
    {
        return $this->backups;
    }

    public function getBackupSchedule(): ?BackupScheduleData
    {
        return $this->backupSchedule;
    }

    public function getTenantName(): ?string
    {
        return $this->tenantName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'database' => $this->database,
            'connection' => $this->connection,
            'backups' => $this->backups,
            'backupSchedule' => $this->backupSchedule,
            'tenantName' => $this->tenantName,
        ];
    }
}
