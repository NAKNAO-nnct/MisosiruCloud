<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Enums\DatabaseType;
use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use App\Services\VmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly VmService $vmService,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $formOptions = $this->vmService->getFormOptions();

        return view('dbaas.create', array_merge([
            'tenants' => $this->tenantRepository->all(),
            'dbTypes' => DatabaseType::cases(),
            'templates' => $this->vmService->listAllVms(),
        ], $formOptions));
    }
}
