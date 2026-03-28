<?php

declare(strict_types=1);

namespace App\Data\Vm;

use App\Enums\VmStatus;
use App\Models\VmMeta;

final readonly class VmMetaData
{
    private function __construct(
        private int $id,
        private int $tenantId,
        private int $proxmoxVmid,
        private string $proxmoxNode,
        private ?string $purpose,
        private string $label,
        private ?string $sharedIpAddress,
        private VmStatus $provisioningStatus,
        private ?string $provisioningError,
        private ?string $tenantName,
    ) {
    }

    /**
     * Eloquent Model から生成 (Repository 内部でのみ使用).
     */
    public static function of(VmMeta $model): self
    {
        return new self(
            id: $model->id,
            tenantId: (int) $model->tenant_id,
            proxmoxVmid: (int) $model->proxmox_vmid,
            proxmoxNode: $model->proxmox_node,
            purpose: $model->purpose,
            label: $model->label,
            sharedIpAddress: $model->shared_ip_address,
            provisioningStatus: $model->provisioning_status,
            provisioningError: $model->provisioning_error,
            tenantName: $model->relationLoaded('tenant') ? $model->tenant?->name : null,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $status = $attributes['provisioning_status'] ?? VmStatus::Pending;

        return new self(
            id: (int) ($attributes['id'] ?? 0),
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            proxmoxVmid: (int) ($attributes['proxmox_vmid'] ?? 0),
            proxmoxNode: (string) ($attributes['proxmox_node'] ?? ''),
            purpose: isset($attributes['purpose']) ? (string) $attributes['purpose'] : null,
            label: (string) ($attributes['label'] ?? ''),
            sharedIpAddress: isset($attributes['shared_ip_address']) ? (string) $attributes['shared_ip_address'] : null,
            provisioningStatus: $status instanceof VmStatus ? $status : VmStatus::from((string) $status),
            provisioningError: isset($attributes['provisioning_error']) ? (string) $attributes['provisioning_error'] : null,
            tenantName: isset($attributes['tenant_name']) ? (string) $attributes['tenant_name'] : null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getProxmoxVmid(): int
    {
        return $this->proxmoxVmid;
    }

    public function getProxmoxNode(): string
    {
        return $this->proxmoxNode;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSharedIpAddress(): ?string
    {
        return $this->sharedIpAddress;
    }

    public function getProvisioningStatus(): VmStatus
    {
        return $this->provisioningStatus;
    }

    public function getProvisioningError(): ?string
    {
        return $this->provisioningError;
    }

    public function getTenantName(): ?string
    {
        return $this->tenantName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'proxmox_vmid' => $this->proxmoxVmid,
            'proxmox_node' => $this->proxmoxNode,
            'purpose' => $this->purpose,
            'label' => $this->label,
            'shared_ip_address' => $this->sharedIpAddress,
            'provisioning_status' => $this->provisioningStatus,
            'provisioning_error' => $this->provisioningError,
        ];
    }
}
