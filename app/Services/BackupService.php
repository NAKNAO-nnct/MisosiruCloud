<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Dbaas\DatabaseInstanceData;
use App\Repositories\BackupScheduleRepository;
use App\Repositories\S3CredentialRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BackupService
{
    public function __construct(
        private readonly S3CredentialRepository $s3CredentialRepository,
        private readonly BackupScheduleRepository $backupScheduleRepository,
    ) {
    }

    public function executeBackup(DatabaseInstanceData $db): void
    {
        $schedule = $this->backupScheduleRepository->findByDatabaseInstanceId($db->getId());

        if ($schedule) {
            $this->backupScheduleRepository->update($schedule->getId(), ['last_backup_status' => 'running']);
        }

        try {
            $s3Credential = $this->s3CredentialRepository->findActiveByTenantId($db->getTenantId());

            if (!$s3Credential) {
                throw new RuntimeException('No active S3 credential found for tenant.');
            }

            $timestamp = now()->format('YmdHis');
            $s3Key = "backups/{$db->getId()}/{$db->getDbType()->value}-{$timestamp}.sql.enc";

            $s3Endpoint = config('services.s3_proxy.url', 'http://localhost:9000');

            Http::withHeaders([
                'X-Access-Key' => $s3Credential->getAccessKey(),
                'X-Secret-Key' => $s3Credential->getSecretKey(),
            ])->put("{$s3Endpoint}/{$s3Credential->getAllowedBucket()}/{$s3Key}", [
                'db_instance_id' => $db->getId(),
                'db_type' => $db->getDbType()->value,
                'timestamp' => $timestamp,
            ]);

            if ($schedule) {
                $this->backupScheduleRepository->update($schedule->getId(), [
                    'last_backup_at' => now(),
                    'last_backup_status' => 'success',
                    'last_backup_size_bytes' => 0,
                ]);
            }
        } catch (Throwable $e) {
            Log::error("Backup failed for DatabaseInstance {$db->getId()}: {$e->getMessage()}");

            if ($schedule) {
                $this->backupScheduleRepository->update($schedule->getId(), ['last_backup_status' => 'failed']);
            }

            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(DatabaseInstanceData $db): array
    {
        $s3Credential = $this->s3CredentialRepository->findActiveByTenantId($db->getTenantId());

        if (!$s3Credential) {
            return [];
        }

        $s3Endpoint = config('services.s3_proxy.url', 'http://localhost:9000');

        $response = Http::withHeaders([
            'X-Access-Key' => $s3Credential->getAccessKey(),
            'X-Secret-Key' => $s3Credential->getSecretKey(),
        ])->get("{$s3Endpoint}/{$s3Credential->getAllowedBucket()}", [
            'prefix' => "backups/{$db->getId()}/",
        ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('objects', []);
    }

    public function restore(DatabaseInstanceData $db, string $s3Key): void
    {
        $s3Credential = $this->s3CredentialRepository->findActiveByTenantId($db->getTenantId());

        if (!$s3Credential) {
            throw new RuntimeException('No active S3 credential found for tenant.');
        }

        $s3Endpoint = config('services.s3_proxy.url', 'http://localhost:9000');

        Http::withHeaders([
            'X-Access-Key' => $s3Credential->getAccessKey(),
            'X-Secret-Key' => $s3Credential->getSecretKey(),
        ])->post("{$s3Endpoint}/restore", [
            'db_instance_id' => $db->getId(),
            's3_key' => $s3Key,
        ]);
    }

    public function pruneOldBackups(DatabaseInstanceData $db): void
    {
        $schedule = $this->backupScheduleRepository->findByDatabaseInstanceId($db->getId());

        if (!$schedule) {
            return;
        }

        $backups = $this->listBackups($db);
        $cutoffDaily = now()->subDays($schedule->getRetentionDaily());
        $cutoffWeekly = now()->subWeeks($schedule->getRetentionWeekly());
        $cutoffMonthly = now()->subMonths($schedule->getRetentionMonthly());

        $s3Credential = $this->s3CredentialRepository->findActiveByTenantId($db->getTenantId());

        if (!$s3Credential) {
            return;
        }

        $s3Endpoint = config('services.s3_proxy.url', 'http://localhost:9000');

        foreach ($backups as $backup) {
            $createdAt = Carbon::parse($backup['last_modified'] ?? $backup['created_at'] ?? null);

            if (!$createdAt) {
                continue;
            }

            $isWeekly = $createdAt->dayOfWeek === 0;
            $isMonthly = $createdAt->day === 1;

            $shouldDelete = match (true) {
                $isMonthly => $createdAt->lt($cutoffMonthly),
                $isWeekly => $createdAt->lt($cutoffWeekly),
                default => $createdAt->lt($cutoffDaily),
            };

            if ($shouldDelete) {
                Http::withHeaders([
                    'X-Access-Key' => $s3Credential->getAccessKey(),
                    'X-Secret-Key' => $s3Credential->getSecretKey(),
                ])->delete("{$s3Endpoint}/{$s3Credential->getAllowedBucket()}/{$backup['key']}");
            }
        }
    }
}
