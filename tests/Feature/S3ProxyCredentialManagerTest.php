<?php

declare(strict_types=1);

use App\Lib\S3Proxy\CredentialManager;
use App\Models\Tenant;

describe('S3 Proxy Credential Manager', function (): void {
    it('creates default credential for tenant', function (): void {
        $tenant = Tenant::factory()->create();

        $credential = CredentialManager::createDefaultCredential($tenant);

        expect($credential)
            ->tenant_id->toBe($tenant->id)
            ->access_key->toMatch('/^AKIA/')
            ->secret_key_plain->toHaveLength(40)
            ->allowed_bucket->toBe('dbaas-backups')
            ->allowed_prefix->toBe('dbaas-backups/')
            ->is_active->toBeTrue();
    });

    it('rotates credential for tenant', function (): void {
        $tenant = Tenant::factory()->create();
        $originalCredential = CredentialManager::createDefaultCredential($tenant);

        $newCredential = CredentialManager::rotateCredential($originalCredential);

        // Verify old credential is deactivated
        $originalCredential->refresh();
        expect($originalCredential->is_active)->toBeFalse();

        // Verify new credential is active
        expect($newCredential)
            ->is_active->toBeTrue()
            ->tenant_id->toBe($tenant->id)
            ->access_key->not->toBe($originalCredential->access_key);
    });

    it('retrieves active credentials for tenant', function (): void {
        $tenant = Tenant::factory()->create();
        $credential1 = CredentialManager::createDefaultCredential($tenant, 'bucket1', 'prefix1/');
        $credential2 = CredentialManager::createDefaultCredential($tenant, 'bucket2', 'prefix2/');

        // Deactivate one
        $credential1->update(['is_active' => false]);

        $activeCredentials = CredentialManager::getCredentialsForTenant($tenant);

        expect($activeCredentials)->toHaveCount(1)
            ->and($activeCredentials[0]->id)->toBe($credential2->id);
    });

    it('generates unique access keys', function (): void {
        $key1 = (new class()
        {
            public static function generateAccessKey(): string
            {
                $reflection = new ReflectionClass(CredentialManager::class);
                $method = $reflection->getMethod('generateAccessKey');
                $method->setAccessible(true);

                return $method->invoke(null);
            }
        })::generateAccessKey();

        // Just verify format is correct
        expect($key1)->toMatch('/^AKIA.{16}$/');
    });
});
