<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Repositories\S3CredentialRepository;
use App\Services\S3CredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RotateController extends Controller
{
    public function __construct(
        private readonly S3CredentialService $s3Service,
        private readonly S3CredentialRepository $s3CredentialRepository,
    ) {
    }

    public function __invoke(Request $request, int $tenant, int $s3Credential): RedirectResponse
    {
        $s3CredentialData = $this->s3CredentialRepository->findByIdOrFail($s3Credential);

        abort_unless($s3CredentialData->getTenantId() === $tenant, 404);

        $this->s3Service->rotate($s3CredentialData);

        return redirect()->route('tenants.s3-credentials.show', [$tenant, $s3Credential])
            ->with('success', 'S3シークレットキーをローテーションしました。');
    }
}
