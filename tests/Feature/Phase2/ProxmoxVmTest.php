<?php

declare(strict_types=1);

use App\Lib\Proxmox\Client;
use App\Lib\Proxmox\DataObjects\VmStatus;
use App\Lib\Proxmox\Resources\Vm;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('getVmStatus maps response to VmStatus dataobject', function (): void {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'status' => 'running',
                'cpus' => 2,
                'maxcpu' => 4,
                'mem' => 1073741824,
                'maxmem' => 2147483648,
                'uptime' => 3600,
                'pid' => 12345,
            ],
        ], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $vm = new Vm($client);
    $status = $vm->getVmStatus('pve1', 100);

    expect($status)->toBeInstanceOf(VmStatus::class)
        ->and($status->status)->toBe('running')
        ->and($status->cpus)->toBe(2)
        ->and($status->maxcpu)->toBe(4)
        ->and($status->mem)->toBe(1073741824)
        ->and($status->maxmem)->toBe(2147483648)
        ->and($status->uptime)->toBe(3600)
        ->and($status->pid)->toBe(12345);
});

test('getVmStatus handles stopped vm without pid', function (): void {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'status' => 'stopped',
                'cpus' => 0,
                'maxcpu' => 2,
                'mem' => 0,
                'maxmem' => 1073741824,
                'uptime' => 0,
            ],
        ], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $vm = new Vm($client);
    $status = $vm->getVmStatus('pve1', 100);

    expect($status->status)->toBe('stopped')
        ->and($status->pid)->toBeNull();
});

test('waitForTask returns true when task succeeds', function (): void {
    Http::fake([
        '*' => Http::response([
            'data' => ['status' => 'stopped', 'exitstatus' => 'OK'],
        ], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $vm = new Vm($client);
    $result = $vm->waitForTask('pve1', 'UPID:pve1:00001234:00000001:67890123:qmstart:100:root@pam:');

    expect($result)->toBeTrue();
});

test('waitForTask returns false when task fails', function (): void {
    Http::fake([
        '*' => Http::response([
            'data' => ['status' => 'stopped', 'exitstatus' => 'Error: failed'],
        ], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $vm = new Vm($client);
    $result = $vm->waitForTask('pve1', 'UPID:pve1:00001234:00000001:67890123:qmstart:100:root@pam:');

    expect($result)->toBeFalse();
});

test('waitForTask returns false on timeout', function (): void {
    Http::fake([
        '*' => Http::response([
            'data' => ['status' => 'running'],
        ], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $vm = new Vm($client);

    // タイムアウトを1秒に設定して素早く終了させる
    $result = $vm->waitForTask('pve1', 'UPID:pve1:0000:0000:0000:test:100:root@pam:', timeout: 1);

    expect($result)->toBeFalse();
});
