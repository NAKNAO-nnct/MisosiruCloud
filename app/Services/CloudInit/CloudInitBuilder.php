<?php

declare(strict_types=1);

namespace App\Services\CloudInit;

use App\Models\Tenant;

class CloudInitBuilder
{
    /**
     * @param array<string, mixed> $opts
     */
    public function buildUserData(array $opts = []): string
    {
        $packages = $opts['packages'] ?? [];
        $runcmd = $opts['runcmd'] ?? [];

        $lines = ['#cloud-config', 'package_update: true', 'package_upgrade: false'];

        if (!empty($packages)) {
            $lines[] = 'packages:';
            foreach ($packages as $pkg) {
                $lines[] = "  - {$pkg}";
            }
        }

        if (!empty($runcmd)) {
            $lines[] = 'runcmd:';
            foreach ($runcmd as $cmd) {
                $escaped = str_replace("'", "''", $cmd);
                $lines[] = "  - '{$escaped}'";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public function buildNetworkConfig(Tenant $tenant, string $ip, string $gateway): string
    {
        return implode("\n", [
            'network:',
            '  version: 2',
            '  ethernets:',
            '    eth0:',
            "      addresses: [{$ip}/24]",
            "      gateway4: {$gateway}",
            '      nameservers:',
            '        addresses: [8.8.8.8, 8.8.4.4]',
        ]) . "\n";
    }
}
