<?php

declare(strict_types=1);

use App\Lib\Proxmox\Client;
use App\Lib\Proxmox\Exceptions\ProxmoxApiException;
use App\Lib\Proxmox\Exceptions\ProxmoxAuthException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('client sends correct authorization header', function (): void {
    Http::fake([
        'https://proxmox.example.com:8006/api2/json/nodes' => Http::response(
            ['data' => [['node' => 'pve1', 'status' => 'online']]],
            200,
        ),
    ]);

    $client = new Client('proxmox.example.com', 'user@pve!token', 'secret123');
    $client->get('nodes');

    Http::assertSent(fn ($request) => $request->header('Authorization')[0] === 'PVEAPIToken=user@pve!token=secret123');
});

test('client uses explicit port from hostname when provided', function (): void {
    Http::fake([
        'https://proxmox.example.com:9443/api2/json/nodes' => Http::response(
            ['data' => [['node' => 'pve1', 'status' => 'online']]],
            200,
        ),
    ]);

    $client = new Client('proxmox.example.com:9443', 'user@pve!token', 'secret123');
    $client->get('nodes');

    Http::assertSent(fn ($request) => $request->url() === 'https://proxmox.example.com:9443/api2/json/nodes');
});

test('client get returns data array from response', function (): void {
    Http::fake([
        '*' => Http::response(['data' => [['node' => 'pve1'], ['node' => 'pve2']]], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $result = $client->get('nodes');

    expect($result)->toBe([['node' => 'pve1'], ['node' => 'pve2']]);
});

test('client get wraps scalar data into data key', function (): void {
    Http::fake([
        '*' => Http::response(['data' => '123'], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $result = $client->get('cluster/nextid');

    expect($result)->toBe(['data' => '123']);
});

test('client throws ProxmoxAuthException on 401', function (): void {
    Http::fake([
        '*' => Http::response([], 401),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'wrong');

    expect(fn () => $client->get('nodes'))->toThrow(ProxmoxAuthException::class);
});

test('client throws ProxmoxAuthException on 403', function (): void {
    Http::fake([
        '*' => Http::response([], 403),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'wrong');

    expect(fn () => $client->get('cluster/sdn/vnets'))->toThrow(ProxmoxAuthException::class);
});

test('client throws ProxmoxApiException on 500', function (): void {
    Http::fake([
        '*' => Http::response(['errors' => ['msg' => 'internal error']], 500),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');

    expect(fn () => $client->get('nodes'))->toThrow(ProxmoxApiException::class);
});

test('ProxmoxAuthException is subclass of ProxmoxApiException', function (): void {
    $e = new ProxmoxAuthException('test');

    expect($e)->toBeInstanceOf(ProxmoxApiException::class);
});

test('client post sends POST request', function (): void {
    Http::fake([
        '*' => Http::response(['data' => ['upid' => 'UPID:pve1:1234']], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $result = $client->post('nodes/pve1/qemu', ['vmid' => 100, 'name' => 'test-vm']);

    expect($result)->toHaveKey('upid');
    Http::assertSent(fn ($request) => $request->method() === 'POST');
});

test('client post supports scalar upid response', function (): void {
    Http::fake([
        '*' => Http::response(['data' => 'UPID:pve1:1234:5678:startall::root@pam:'], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $result = $client->post('nodes/pve1/status/startall');

    expect($result['upid'] ?? null)->toBe('UPID:pve1:1234:5678:startall::root@pam:');
});

test('client delete sends DELETE request', function (): void {
    Http::fake([
        '*' => Http::response(['data' => []], 200),
    ]);

    $client = new Client('pve.local', 'root@pam!test', 'abc');
    $client->delete('nodes/pve1/qemu/100');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE');
});
