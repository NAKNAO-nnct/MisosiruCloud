<?php

declare(strict_types=1);

namespace App\Services\CloudInit;

final class CloudInitBuilder
{
    /**
     * @param list<string> $dnsResolvers
     */
    public function __construct(
        private readonly array $dnsResolvers = ['1.1.1.1', '8.8.8.8'],
    ) {
    }

    /**
     * cloud-config YAML（user-data）を生成する。
     *
     * @param array{
     *     hostname: string,
     *     fqdn: string,
     *     ssh_keys?: string|null,
     *     packages?: list<string>,
     *     timezone?: string,
     *     dns_resolvers?: list<string>,
     *     runcmd?: list<string>,
     * } $config
     */
    public function buildUserData(array $config): string
    {
        $hostname = $config['hostname'];
        $fqdn = $config['fqdn'];
        $sshKeys = $config['ssh_keys'] ?? null;
        $packages = $config['packages'] ?? ['qemu-guest-agent'];
        $timezone = $config['timezone'] ?? 'Asia/Tokyo';
        $dnsResolvers = $config['dns_resolvers'] ?? $this->dnsResolvers;
        $extraRuncmd = $config['runcmd'] ?? [];

        $lines = [
            '#cloud-config',
            'hostname: ' . $hostname,
            'fqdn: ' . $fqdn,
            'manage_etc_hosts: true',
            'timezone: ' . $timezone,
            'packages:',
        ];

        foreach ($packages as $package) {
            $lines[] = '  - ' . $package;
        }

        $lines[] = 'package_update: true';
        $lines[] = 'resolv_conf:';
        $lines[] = '  nameservers:';

        foreach ((array) $dnsResolvers as $resolver) {
            $lines[] = '    - ' . $resolver;
        }

        if ($sshKeys !== null && $sshKeys !== '') {
            $lines[] = 'ssh_authorized_keys:';

            foreach (array_filter(array_map('trim', explode("\n", $sshKeys))) as $key) {
                $lines[] = '  - ' . $key;
            }
        }

        $runcmd = array_merge(
            ['systemctl enable qemu-guest-agent', 'systemctl start qemu-guest-agent'],
            $extraRuncmd,
        );

        $lines[] = 'runcmd:';

        foreach ($runcmd as $cmd) {
            $lines[] = "  - '" . $cmd . "'";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Netplan v2 network-config YAML を生成する。
     */
    public function buildNetworkConfig(
        string $ipCidr,
        string $gateway,
        ?string $sharedIp = null,
    ): string {
        $resolvers = $this->dnsResolvers;

        $lines = [
            'version: 2',
            'ethernets:',
            '  eth0:',
            '    addresses:',
            '      - ' . $ipCidr,
            '    routes:',
            '      - to: default',
            '        via: ' . $gateway,
            '    nameservers:',
            '      addresses:',
        ];

        foreach ((array) $resolvers as $resolver) {
            $lines[] = '        - ' . $resolver;
        }

        if ($sharedIp !== null && $sharedIp !== '') {
            $lines[] = '  eth1:';
            $lines[] = '    addresses:';
            $lines[] = '      - ' . $sharedIp . '/32';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * cloud-init meta-data YAML を生成する。
     */
    public function buildMetaData(int $vmId, string $hostname): string
    {
        return implode("\n", [
            'instance-id: vm-' . $vmId,
            'local-hostname: ' . $hostname,
            '',
        ]);
    }
}
