<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Models\S3Credential;
use App\Models\Tenant;
use App\Services\S3CredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(private readonly S3CredentialService $s3Service)
    {
    }

    public function __invoke(Request $request, Tenant $tenant, S3Credential $s3Credential): RedirectResponse
    {
        abort_unless($s3Credential->tenant_id === $tenant->id, 404);

        $this->s3Service->deactivate($s3Credential);

        return redirect()->route('tenants.s3-credentials.index', $tenant)
            ->with('success', 'S3認証情報を無効化しました。');
    }
}
