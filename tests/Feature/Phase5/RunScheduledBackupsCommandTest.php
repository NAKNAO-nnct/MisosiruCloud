<?php

declare(strict_types=1);

use App\Models\BackupSchedule;
use App\Models\DatabaseInstance;
use App\Services\BackupService;

test('enabled and due schedule triggers backup and prune', function (): void {
    $db = DatabaseInstance::factory()->create();

    BackupSchedule::query()->create([
        'database_instance_id' => $db->id,
        'cron_expression' => '* * * * *',
        'retention_daily' => 7,
        'retention_weekly' => 4,
        'retention_monthly' => 3,
        'is_enabled' => true,
    ]);

    $backupService = Mockery::mock(BackupService::class);
    $backupService->shouldReceive('executeBackup')->once();
    $backupService->shouldReceive('pruneOldBackups')->once();
    $this->app->instance(BackupService::class, $backupService);

    $this->artisan('backups:run-scheduled')
        ->expectsOutput("Backup completed for database instance {$db->id}")
        ->assertExitCode(0);
});

test('disabled schedule does not trigger backup', function (): void {
    $db = DatabaseInstance::factory()->create();

    BackupSchedule::query()->create([
        'database_instance_id' => $db->id,
        'cron_expression' => '* * * * *',
        'retention_daily' => 7,
        'retention_weekly' => 4,
        'retention_monthly' => 3,
        'is_enabled' => false,
    ]);

    $backupService = Mockery::mock(BackupService::class);
    $backupService->shouldNotReceive('executeBackup');
    $backupService->shouldNotReceive('pruneOldBackups');
    $this->app->instance(BackupService::class, $backupService);

    $this->artisan('backups:run-scheduled')->assertExitCode(0);
});
