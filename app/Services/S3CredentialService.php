<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\S3Credential;
use App\Models\Tenant;
use Illuminate\Support\Str;

class S3CredentialService
{
    public function generateAccessKey(): string
    {
        return 'MSIR' . mb_strtoupper(Str::random(16));
    }

    public function generateSecretKey(): string
    {
        return Str::random(40);
    }

    public function createForTenant(
        Tenant $tenant,
        string $bucket,
        string $prefix,
        string $description,
    ): S3Credential {
        return $tenant->s3Credentials()->create([
            'access_key' => $this->generateAccessKey(),
            'secret_key_encrypted' => $this->generateSecretKey(),
            'allowed_bucket' => $bucket,
            'allowed_prefix' => $prefix,
            'description' => $description,
            'is_active' => true,
        ]);
    }

    public function rotate(S3Credential $credential): S3Credential
    {
        $credential->update([
            'access_key' => $this->generateAccessKey(),
            'secret_key_encrypted' => $this->generateSecretKey(),
        ]);

        return $credential->fresh();
    }

    public function deactivate(S3Credential $credential): void
    {
        $credential->update(['is_active' => false]);
    }
}
