<?php

declare(strict_types=1);

use App\Services\CloudInit\CloudInitBuilder;

test('buildUserData が #cloud-config ヘッダーと hostname を含む YAML を返す', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'hostname' => 'my-vm',
        'fqdn' => 'my-vm.example.local',
    ]);

    expect($yaml)
        ->toStartWith('#cloud-config')
        ->toContain('hostname: my-vm')
        ->toContain('fqdn: my-vm.example.local')
        ->toContain('manage_etc_hosts: true');
});

test('buildUserData はデフォルトで qemu-guest-agent をパッケージに含む', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'hostname' => 'test-vm',
        'fqdn' => 'test-vm.local',
    ]);

    expect($yaml)
        ->toContain('packages:')
        ->toContain('- qemu-guest-agent')
        ->toContain('package_update: true');
});

test('buildUserData にカスタムパッケージを渡すと packages セクションに反映される', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'hostname' => 'db-vm',
        'fqdn' => 'db-vm.local',
        'packages' => ['mysql-server', 'curl', 'qemu-guest-agent'],
    ]);

    expect($yaml)
        ->toContain('- mysql-server')
        ->toContain('- curl');
});

test('buildUserData に ssh_keys を渡すと ssh_authorized_keys セクションが生成される', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'hostname' => 'test-vm',
        'fqdn' => 'test-vm.local',
        'ssh_keys' => 'ssh-rsa AAAAB3NzaC1yc user@host',
    ]);

    expect($yaml)
        ->toContain('ssh_authorized_keys:')
        ->toContain('- ssh-rsa AAAAB3NzaC1yc user@host');
});

test('buildUserData に timezone を渡すと timezone が設定される', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'hostname' => 'test-vm',
        'fqdn' => 'test-vm.local',
        'timezone' => 'UTC',
    ]);

    expect($yaml)->toContain('timezone: UTC');
});

test('buildUserData に ssh_keys が null の場合は ssh_authorized_keys セクションを含まない', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'hostname' => 'test-vm',
        'fqdn' => 'test-vm.local',
        'ssh_keys' => null,
    ]);

    expect($yaml)->not->toContain('ssh_authorized_keys:');
});

test('buildNetworkConfig が Netplan v2 YAML を返す', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildNetworkConfig('10.1.0.10/24', '10.1.0.1');

    expect($yaml)
        ->toContain('version: 2')
        ->toContain('ethernets:')
        ->toContain('eth0:')
        ->toContain('- 10.1.0.10/24')
        ->toContain('via: 10.1.0.1');
});

test('buildNetworkConfig に sharedIp を渡すと eth1 セクションが追加される', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildNetworkConfig('10.1.0.10/24', '10.1.0.1', '203.0.113.5');

    expect($yaml)
        ->toContain('eth1:')
        ->toContain('- 203.0.113.5/32');
});

test('buildNetworkConfig に sharedIp がない場合は eth1 を含まない', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildNetworkConfig('10.1.0.10/24', '10.1.0.1');

    expect($yaml)->not->toContain('eth1:');
});

test('buildMetaData が instance-id と local-hostname を含む YAML を返す', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildMetaData(300, 'my-vm');

    expect($yaml)
        ->toContain('instance-id: vm-300')
        ->toContain('local-hostname: my-vm');
});
