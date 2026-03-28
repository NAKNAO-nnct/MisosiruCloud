<?php

declare(strict_types=1);

namespace App\Lib\S3Proxy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CredentialManager
{
    /**
     * Create default S3 credential for a tenant.
     *
     * @param string $bucket Default bucket for backups
     * @param string $prefix Default key prefix (e.g., 'dbaas-backups/')
     */
    public static function createDefaultCredential(
        Tenant $tenant,
        string $bucket = 'dbaas-backups',
        string $prefix = 'dbaas-backups/',
    ): \App\Models\S3Credential {
        $accessKey = self::generateAccessKey();
        $secretKey = self::generateSecretKey();

        return DB::transaction(function () use ($tenant, $bucket, $prefix, $accessKey, $secretKey) {
            $credential = new \App\Models\S3Credential();
            $credential->tenant_id = $tenant->id;
            $credential->name = "Default S3 Backup Credential";
            $credential->access_key = $accessKey;
            $credential->secret_key_plain = $secretKey;
            $credential->allowed_bucket = $bucket;
            $credential->allowed_prefix = $prefix;
            $credential->is_active = true;
            $credential->save();

            return $credential;
        });
    }

    /**
     * Rotate credential for a tenant (create new, deactivate old).
     */
    public static function rotateCredential(\App\Models\S3Credential $credential): \App\Models\S3Credential
    {
        return DB::transaction(function () use ($credential) {
            // Deactivate old credential
            $credential->update(['is_active' => false]);

            // Create new credential with same tenant and permissions
            return self::createDefaultCredential(
                $credential->tenant,
                $credential->allowed_bucket,
                $credential->allowed_prefix,
            );
        });
    }

    /**
     * Get all active credentials for a tenant.
     */
    public static function getCredentialsForTenant(Tenant $tenant): Collection
    {
        return $tenant->s3Credentials()
            ->where('is_active', true)
            ->get();
    }

    /**
     * Generate a random access key
     * Format: AKIA + 16 random chars (AWS-like format).
     */
    private static function generateAccessKey(): string
    {
        return 'AKIA' . Str::random(16);
    }

    /**
     * Generate a random secret key
     * Format: 40 random chars (AWS-like format).
     */
    private static function generateSecretKey(): string
    {
        return Str::random(40);
    }
}
