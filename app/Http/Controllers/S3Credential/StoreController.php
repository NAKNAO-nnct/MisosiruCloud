<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\S3CredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private readonly S3CredentialService $s3Service)
    {
    }

    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'allowed_bucket' => ['required', 'string', 'max:255'],
            'allowed_prefix' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $this->s3Service->createForTenant(
            tenant: $tenant,
            bucket: $validated['allowed_bucket'],
            prefix: $validated['allowed_prefix'],
            description: $validated['description'] ?? '',
        );

        return redirect()->route('tenants.s3-credentials.index', $tenant)
            ->with('success', 'S3認証情報を作成しました。');
    }
}
