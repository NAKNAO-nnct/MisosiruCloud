<?php

declare(strict_types=1);

use App\Data\Dbaas\ProvisionDbaasCommand;
use App\Jobs\DestroyVmJob;
use App\Jobs\ProvisionDbaasJob;
use App\Jobs\ProvisionVmJob;
use Tests\TestCase;

uses(TestCase::class);

test('provisioning queue connection is configured', function (): void {
    $connection = config('queue.connections.provisioning');

    expect($connection)->toBeArray()
        ->and($connection['driver'])->toBe('database')
        ->and($connection['queue'])->toBe('provisioning')
        ->and($connection['table'])->toBe('jobs');
});

test('provisioning jobs use expected connection, timeout and tries', function (): void {
    $provisionVmJob = new ProvisionVmJob(201, ['template_vmid' => 9000, 'node' => 'pve1']);
    $provisionDbaasJob = new ProvisionDbaasJob(ProvisionDbaasCommand::make([
        'tenant_id' => 1,
        'db_type' => 'mysql',
        'db_version' => '8.4',
        'template_vmid' => 9000,
        'node' => 'pve1',
        'new_vmid' => 701,
        'cpu' => 2,
        'memory_mb' => 2048,
    ]));
    $destroyVmJob = new DestroyVmJob(1);

    expect($provisionVmJob->connection)->toBe('provisioning')
        ->and($provisionVmJob->queue)->toBe('provisioning')
        ->and($provisionVmJob->timeout)->toBe(600)
        ->and($provisionVmJob->tries)->toBe(1)
        ->and($provisionDbaasJob->connection)->toBe('provisioning')
        ->and($provisionDbaasJob->queue)->toBe('provisioning')
        ->and($provisionDbaasJob->timeout)->toBe(600)
        ->and($provisionDbaasJob->tries)->toBe(1)
        ->and($destroyVmJob->connection)->toBe('provisioning')
        ->and($destroyVmJob->queue)->toBe('provisioning')
        ->and($destroyVmJob->timeout)->toBe(600)
        ->and($destroyVmJob->tries)->toBe(1);
});
