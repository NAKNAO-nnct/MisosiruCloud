<?php

declare(strict_types=1);

use App\Data\Dbaas\BackupScheduleData;
use App\Data\Dbaas\DatabaseInstanceData;
use App\Data\S3\S3CredentialData;
use App\Repositories\BackupScheduleRepository;
use App\Repositories\S3CredentialRepository;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('pruneOldBackups keeps recent backups and deletes backups past retention windows', function (): void {
    Carbon::setTestNow('2026-03-28 12:00:00');

    config()->set('services.s3_proxy.url', 'http://localhost:9000');

    $db = DatabaseInstanceData::make([
        'id' => 501,
        'tenant_id' => 77,
        'vm_meta_id' => 1,
        'db_type' => 'mysql',
        'db_version' => '8.4',
        'port' => 3306,
        'admin_user' => 'admin',
        'admin_password' => 'secret',
        'tenant_user' => '',
        'tenant_password' => '',
        'backup_encryption_key' => 'key',
        'status' => 'running',
    ]);

    $schedule = BackupScheduleData::make([
        'id' => 9,
        'database_instance_id' => 501,
        'retention_daily' => 7,
        'retention_weekly' => 4,
        'retention_monthly' => 3,
        'is_enabled' => true,
    ]);

    $credential = S3CredentialData::make([
        'id' => 1,
        'tenant_id' => 77,
        'access_key' => 'MSIRTESTKEY',
        'secret_key' => 'secret-key',
        'allowed_bucket' => 'dbaas-backups',
        'allowed_prefix' => 'tenant-77/',
        'description' => 'test',
        'is_active' => true,
    ]);

    $backupScheduleRepository = Mockery::mock(BackupScheduleRepository::class);
    $backupScheduleRepository->shouldReceive('findByDatabaseInstanceId')
        ->once()
        ->with(501)
        ->andReturn($schedule);

    $s3CredentialRepository = Mockery::mock(S3CredentialRepository::class);
    $s3CredentialRepository->shouldReceive('findActiveByTenantId')
        ->times(2)
        ->with(77)
        ->andReturn($credential);

    Http::fake([
        'http://localhost:9000/dbaas-backups*' => Http::sequence()
            ->push([
                'objects' => [
                    ['key' => 'backups/501/daily-old.sql.enc', 'created_at' => '2026-03-20T00:00:00Z'],
                    ['key' => 'backups/501/daily-keep.sql.enc', 'created_at' => '2026-03-25T00:00:00Z'],
                    ['key' => 'backups/501/weekly-old.sql.enc', 'created_at' => '2026-02-15T00:00:00Z'],
                    ['key' => 'backups/501/weekly-keep.sql.enc', 'created_at' => '2026-03-22T00:00:00Z'],
                    ['key' => 'backups/501/monthly-old.sql.enc', 'created_at' => '2025-11-01T00:00:00Z'],
                    ['key' => 'backups/501/monthly-keep.sql.enc', 'created_at' => '2026-02-01T00:00:00Z'],
                ],
            ], 200)
            ->push([], 204)
            ->push([], 204)
            ->push([], 204),
    ]);

    $service = new BackupService($s3CredentialRepository, $backupScheduleRepository);

    $service->pruneOldBackups($db);

    Http::assertSentCount(4);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), 'backups/501/daily-old.sql.enc'));

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), 'backups/501/weekly-old.sql.enc'));

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), 'backups/501/monthly-old.sql.enc'));

    Carbon::setTestNow();
});

test('pruneOldBackups returns early when backup schedule does not exist', function (): void {
    $db = DatabaseInstanceData::make([
        'id' => 601,
        'tenant_id' => 88,
        'vm_meta_id' => 1,
        'db_type' => 'postgres',
        'db_version' => '17',
        'port' => 5432,
        'admin_user' => 'admin',
        'admin_password' => 'secret',
        'tenant_user' => '',
        'tenant_password' => '',
        'backup_encryption_key' => 'key',
        'status' => 'running',
    ]);

    $backupScheduleRepository = Mockery::mock(BackupScheduleRepository::class);
    $backupScheduleRepository->shouldReceive('findByDatabaseInstanceId')
        ->once()
        ->with(601)
        ->andReturnNull();

    $s3CredentialRepository = Mockery::mock(S3CredentialRepository::class);
    $s3CredentialRepository->shouldNotReceive('findActiveByTenantId');

    Http::fake();

    $service = new BackupService($s3CredentialRepository, $backupScheduleRepository);

    $service->pruneOldBackups($db);

    Http::assertNothingSent();
});
