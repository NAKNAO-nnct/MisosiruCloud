<?php

declare(strict_types=1);

use App\Lib\Nomad\DataObjects\AllocationStatus;
use App\Lib\Nomad\DataObjects\JobSpec;

test('job spec maps from nomad response and back to array', function (): void {
    $spec = JobSpec::from([
        'ID' => 'job-1',
        'Name' => 'job-1',
        'Namespace' => 'tenant-a',
        'Type' => 'service',
        'Datacenters' => ['dc1'],
        'TaskGroups' => [
            ['Name' => 'web'],
        ],
    ]);

    expect($spec->id)->toBe('job-1')
        ->and($spec->namespace)->toBe('tenant-a')
        ->and($spec->toArray()['Datacenters'])->toBe(['dc1']);
});

test('allocation status maps required fields', function (): void {
    $status = AllocationStatus::from([
        'ID' => 'alloc-1',
        'Name' => 'web[0]',
        'JobID' => 'job-1',
        'ClientStatus' => 'running',
        'DesiredStatus' => 'run',
        'TaskStates' => [
            'web' => ['State' => 'running'],
        ],
    ]);

    expect($status->id)->toBe('alloc-1')
        ->and($status->clientStatus)->toBe('running')
        ->and($status->taskStates)->toHaveKey('web');
});
