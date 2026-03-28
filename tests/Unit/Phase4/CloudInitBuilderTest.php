<?php

declare(strict_types=1);

use App\Services\CloudInit\CloudInitBuilder;

test('buildUserData が #cloud-config ヘッダーを含む YAML を返す', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData();

    expect($yaml)->toStartWith('#cloud-config');
});

test('buildUserData にパッケージを渡すと packages セクションが生成される', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData(['packages' => ['mysql-server', 'curl']]);

    expect($yaml)
        ->toContain('packages:')
        ->toContain('- mysql-server')
        ->toContain('- curl');
});

test('buildUserData に runcmd を渡すと runcmd セクションが生成される', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildUserData([
        'runcmd' => ['systemctl enable mysql', 'systemctl start mysql'],
    ]);

    expect($yaml)
        ->toContain('runcmd:')
        ->toContain("'systemctl enable mysql'")
        ->toContain("'systemctl start mysql'");
});

test('buildNetworkConfig が network: キーを含む YAML を返す', function (): void {
    $builder = new CloudInitBuilder();
    $yaml = $builder->buildNetworkConfig('10.1.0.10', '10.1.0.1');

    expect($yaml)
        ->toContain('network:')
        ->toContain('version: 2')
        ->toContain('10.1.0.10/24')
        ->toContain('gateway4: 10.1.0.1');
});
