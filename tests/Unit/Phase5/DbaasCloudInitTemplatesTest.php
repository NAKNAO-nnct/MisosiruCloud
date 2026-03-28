<?php

declare(strict_types=1);

use App\Services\CloudInit\CloudInitBuilder;
use App\Services\CloudInit\Templates\MysqlTemplate;
use App\Services\CloudInit\Templates\PostgresTemplate;
use App\Services\CloudInit\Templates\RedisTemplate;

test('mysql template outputs mysql package and service commands', function (): void {
    $template = new MysqlTemplate(new CloudInitBuilder(), '8.4');
    $yaml = $template->buildUserData();

    expect($yaml)
        ->toContain('- mysql-server')
        ->toContain("'systemctl enable mysql'")
        ->toContain("'systemctl start mysql'");

    expect($template->proxmoxConfig())->toBe([
        'cores' => 2,
        'memory' => 4096,
    ]);
});

test('postgres template outputs versioned postgres package and service commands', function (): void {
    $template = new PostgresTemplate(new CloudInitBuilder(), '17');
    $yaml = $template->buildUserData();

    expect($yaml)
        ->toContain('- postgresql-17')
        ->toContain("'systemctl enable postgresql'")
        ->toContain("'systemctl start postgresql'");

    expect($template->proxmoxConfig())->toBe([
        'cores' => 2,
        'memory' => 4096,
    ]);
});

test('redis template outputs redis package and service commands', function (): void {
    $template = new RedisTemplate(new CloudInitBuilder());
    $yaml = $template->buildUserData();

    expect($yaml)
        ->toContain('- redis-server')
        ->toContain("'systemctl enable redis-server'")
        ->toContain("'systemctl start redis-server'");

    expect($template->proxmoxConfig())->toBe([
        'cores' => 1,
        'memory' => 1024,
    ]);
});
