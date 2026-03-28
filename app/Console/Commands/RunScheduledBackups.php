<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\BackupScheduleRepository;
use App\Repositories\DatabaseInstanceRepository;
use App\Services\BackupService;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Throwable;

class RunScheduledBackups extends Command
{
    protected $signature = 'backups:run-scheduled';

    protected $description = 'Run DB backups based on enabled backup schedules';

    public function __construct(
        private readonly BackupScheduleRepository $backupScheduleRepository,
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
        private readonly BackupService $backupService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $hadError = false;

        foreach ($this->backupScheduleRepository->allEnabled() as $schedule) {
            try {
                $cron = CronExpression::factory($schedule->getCronExpression());

                if (!$cron->isDue($now)) {
                    continue;
                }

                $database = $this->databaseInstanceRepository->findById($schedule->getDatabaseInstanceId());

                if (!$database) {
                    $this->warn("Database instance not found: {$schedule->getDatabaseInstanceId()}");

                    continue;
                }

                $this->backupService->executeBackup($database);
                $this->backupService->pruneOldBackups($database);

                $this->info("Backup completed for database instance {$database->getId()}");
            } catch (Throwable $e) {
                $hadError = true;
                report($e);

                $this->error("Backup failed for schedule {$schedule->getId()}: {$e->getMessage()}");
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }
}
