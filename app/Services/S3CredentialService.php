<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\S3\S3CredentialData;
use App\Data\Tenant\TenantData;
use App\Repositories\S3CredentialRepository;
use Illuminate\Support\Str;

class S3CredentialService
{
    public function __construct(private readonly S3CredentialRepository $s3CredentialRepository)
    {
    }

    public function generateAccessKey(): string
    {
        return 'MSIR' . mb_strtoupper(Str::random(16));
    }

    public function generateSecretKey(): string
    {
        return Str::random(40);
    }

    public function createForTenant(
        TenantData $tenant,
        string $bucket,
        string $prefix,
        string $description,
    ): S3CredentialData {
        return $this->s3CredentialRepository->create($tenant->getId(), [
            'access_key' => $this->generateAccessKey(),
            'secret_key_encrypted' => $this->generateSecretKey(),
            'allowed_bucket' => $bucket,
            'allowed_prefix' => $prefix,
            'description' => $description,
            'is_active' => true,
        ]);
    }

    public function rotate(S3CredentialData $credential): S3CredentialData
    {
        return $this->s3CredentialRepository->update($credential->getId(), [
            'access_key' => $this->generateAccessKey(),
            'secret_key_encrypted' => $this->generateSecretKey(),
        ]);
    }

    public function deactivate(S3CredentialData $credential): void
    {
        $this->s3CredentialRepository->update($credential->getId(), ['is_active' => false]);
    }
}
