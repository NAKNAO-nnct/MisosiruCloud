<?php

declare(strict_types=1);

use App\Lib\S3Proxy\CredentialManager;
use App\Models\S3Credential;
use App\Models\Tenant;

describe('S3 Proxy End-to-End', function (): void {
    it('can create credential and verify it in database with both keys', function (): void {
        $tenant = Tenant::factory()->create();
        $credential = CredentialManager::createDefaultCredential($tenant);

        // Verify in database
        $dbCredential = S3Credential::where('access_key', $credential->access_key)->firstOrFail();
        expect($dbCredential->tenant_id)->toBe($tenant->id)
            ->and($dbCredential->secret_key_plain)->toBe($credential->secret_key_plain);
    });

    it('credentials format matches AWS S3 patterns', function (): void {
        $tenant = Tenant::factory()->create();
        $credential = CredentialManager::createDefaultCredential($tenant);

        // Access key format: AKIA + 16 random alphanumeric chars (case-insensitive)
        // Secret key: 40 random alphanumeric chars
        expect($credential->access_key)
            ->toMatch('/^AKIA[A-Za-z0-9]{16}$/')
            ->and($credential->secret_key_plain)->toHaveLength(40)
            ->toMatch('/^[A-Za-z0-9]{40}$/');
    });

    it('multiple credentials per tenant are managed separately', function (): void {
        $tenant = Tenant::factory()->create();
        $cred1 = CredentialManager::createDefaultCredential($tenant, 'bucket1', 'prefix1/');
        $cred2 = CredentialManager::createDefaultCredential($tenant, 'bucket2', 'prefix2/');

        expect(S3Credential::where('tenant_id', $tenant->id)->count())->toBe(2)
            ->and($cred1->access_key)->not->toBe($cred2->access_key);
    });

    it('deactivated credentials are not returned in active list', function (): void {
        $tenant = Tenant::factory()->create();
        $cred1 = CredentialManager::createDefaultCredential($tenant);
        $cred2 = CredentialManager::createDefaultCredential($tenant);

        $cred1->update(['is_active' => false]);

        $active = CredentialManager::getCredentialsForTenant($tenant);
        expect($active)->toHaveCount(1)
            ->and($active[0]->id)->toBe($cred2->id);
    });
});
