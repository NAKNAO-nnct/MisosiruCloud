<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Repositories\S3CredentialRepository;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly S3CredentialRepository $s3CredentialRepository,
    ) {
    }

    public function __invoke(Request $request, int $tenant, int $s3Credential): View
    {
        $tenantData = $this->tenantRepository->findByIdOrFail($tenant);
        $s3CredentialData = $this->s3CredentialRepository->findByIdOrFail($s3Credential);

        abort_unless($s3CredentialData->getTenantId() === $tenant, 404);

        return view('s3_credentials.show', ['tenant' => $tenantData, 's3Credential' => $s3CredentialData]);
    }
}
