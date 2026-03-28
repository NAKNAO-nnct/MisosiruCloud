<?php

declare(strict_types=1);

namespace App\Data\Dbaas;

use App\Enums\DatabaseType;
use App\Models\DatabaseInstance;

final readonly class DatabaseInstanceData
{
    private function __construct(
        private int $id,
        private int $tenantId,
        private int $vmMetaId,
        private DatabaseType $dbType,
        private string $dbVersion,
        private int $port,
        private string $adminUser,
        private string $adminPassword,
        private string $tenantUser,
        private string $tenantPassword,
        private string $backupEncryptionKey,
        private string $status,
    ) {
    }

    /**
     * Eloquent Model から生成 (Repository 内部でのみ使用).
     */
    public static function of(DatabaseInstance $model): self
    {
        return new self(
            id: $model->id,
            tenantId: (int) $model->tenant_id,
            vmMetaId: (int) $model->vm_meta_id,
            dbType: $model->db_type,
            dbVersion: (string) $model->db_version,
            port: (int) $model->port,
            adminUser: (string) $model->admin_user,
            adminPassword: (string) $model->admin_password_encrypted,
            tenantUser: (string) ($model->tenant_user ?? ''),
            tenantPassword: (string) $model->tenant_password_encrypted,
            backupEncryptionKey: (string) $model->backup_encryption_key_encrypted,
            status: (string) $model->status,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $dbType = $attributes['db_type'] ?? DatabaseType::Mysql;

        return new self(
            id: (int) ($attributes['id'] ?? 0),
            tenantId: (int) ($attributes['tenant_id'] ?? 0),
            vmMetaId: (int) ($attributes['vm_meta_id'] ?? 0),
            dbType: $dbType instanceof DatabaseType ? $dbType : DatabaseType::from((string) $dbType),
            dbVersion: (string) ($attributes['db_version'] ?? ''),
            port: (int) ($attributes['port'] ?? 3306),
            adminUser: (string) ($attributes['admin_user'] ?? 'admin'),
            adminPassword: (string) ($attributes['admin_password'] ?? $attributes['admin_password_encrypted'] ?? ''),
            tenantUser: (string) ($attributes['tenant_user'] ?? ''),
            tenantPassword: (string) ($attributes['tenant_password'] ?? $attributes['tenant_password_encrypted'] ?? ''),
            backupEncryptionKey: (string) ($attributes['backup_encryption_key'] ?? $attributes['backup_encryption_key_encrypted'] ?? ''),
            status: (string) ($attributes['status'] ?? 'running'),
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

    public function getVmMetaId(): int
    {
        return $this->vmMetaId;
    }

    public function getDbType(): DatabaseType
    {
        return $this->dbType;
    }

    public function getDbVersion(): string
    {
        return $this->dbVersion;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getAdminUser(): string
    {
        return $this->adminUser;
    }

    public function getAdminPassword(): string
    {
        return $this->adminPassword;
    }

    public function getTenantUser(): string
    {
        return $this->tenantUser;
    }

    public function getTenantPassword(): string
    {
        return $this->tenantPassword;
    }

    public function getBackupEncryptionKey(): string
    {
        return $this->backupEncryptionKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'vm_meta_id' => $this->vmMetaId,
            'db_type' => $this->dbType,
            'db_version' => $this->dbVersion,
            'port' => $this->port,
            'admin_user' => $this->adminUser,
            'admin_password_encrypted' => $this->adminPassword,
            'tenant_user' => $this->tenantUser,
            'tenant_password_encrypted' => $this->tenantPassword,
            'backup_encryption_key_encrypted' => $this->backupEncryptionKey,
            'status' => $this->status,
        ];
    }
}
