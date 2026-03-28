<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Repositories\S3CredentialRepository;
use App\Repositories\TenantRepository;
use App\Repositories\VmMetaRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly VmMetaRepository $vmMetaRepository,
        private readonly S3CredentialRepository $s3CredentialRepository,
        private readonly DatabaseInstanceRepository $dbInstanceRepository,
    ) {
    }

    public function __invoke(Request $request, int $tenant): View
    {
        $tenantData = $this->tenantRepository->findByIdOrFail($tenant);
        $vmCount = $this->vmMetaRepository->countByTenantId($tenant);
        $s3Count = $this->s3CredentialRepository->countByTenantId($tenant);
        $dbCount = $this->dbInstanceRepository->countByTenantId($tenant);

        return view('tenants.show', [
            'tenant' => $tenantData,
            'vmCount' => $vmCount,
            's3Count' => $s3Count,
            'dbCount' => $dbCount,
        ]);
    }
}
