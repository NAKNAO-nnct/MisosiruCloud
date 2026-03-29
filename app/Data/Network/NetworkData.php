<?php

declare(strict_types=1);

namespace App\Data\Network;

final readonly class NetworkData
{
    private function __construct(
        private int $tenantId,
        private string $tenantName,
        private string $tenantSlug,
        private ?string $vnetName,
        private ?int $vni,
        private ?string $networkCidr,
        private ?string $proxmoxZone,
        private bool $existsOnProxmox,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        return new self(
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            tenantName: (string) ($attributes['tenant_name'] ?? ''),
            tenantSlug: (string) ($attributes['tenant_slug'] ?? ''),
            vnetName: isset($attributes['vnet_name']) ? (string) $attributes['vnet_name'] : null,
            vni: isset($attributes['vni']) ? (int) $attributes['vni'] : null,
            networkCidr: isset($attributes['network_cidr']) ? (string) $attributes['network_cidr'] : null,
            proxmoxZone: isset($attributes['proxmox_zone']) ? (string) $attributes['proxmox_zone'] : null,
            existsOnProxmox: (bool) ($attributes['exists_on_proxmox'] ?? false),
        );
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getTenantName(): string
    {
        return $this->tenantName;
    }

    public function getTenantSlug(): string
    {
        return $this->tenantSlug;
    }

    public function getVnetName(): ?string
    {
        return $this->vnetName;
    }

    public function getVni(): ?int
    {
        return $this->vni;
    }

    public function getNetworkCidr(): ?string
    {
        return $this->networkCidr;
    }

    public function getProxmoxZone(): ?string
    {
        return $this->proxmoxZone;
    }

    public function existsOnProxmox(): bool
    {
        return $this->existsOnProxmox;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'tenant_name' => $this->tenantName,
            'tenant_slug' => $this->tenantSlug,
            'vnet_name' => $this->vnetName,
            'vni' => $this->vni,
            'network_cidr' => $this->networkCidr,
            'proxmox_zone' => $this->proxmoxZone,
            'exists_on_proxmox' => $this->existsOnProxmox,
        ];
    }
}
