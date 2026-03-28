<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Repositories\S3CredentialRepository;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly S3CredentialRepository $s3CredentialRepository,
    ) {
    }

    public function __invoke(Request $request, int $tenant): View
    {
        $tenantData = $this->tenantRepository->findByIdOrFail($tenant);
        $credentials = $this->s3CredentialRepository->paginateByTenantId($tenant);

        return view('s3_credentials.index', ['tenant' => $tenantData, 'credentials' => $credentials]);
    }
}
