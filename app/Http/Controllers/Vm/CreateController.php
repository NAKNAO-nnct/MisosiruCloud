<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use App\Services\VmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateController extends Controller
{
    public function __construct(
        private readonly VmService $vmService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $tenants = $this->tenantRepository->all();
        $templates = $this->vmService->listAllVms();
        $formOptions = $this->vmService->getFormOptions();

        return view('vms.create', array_merge(compact('tenants', 'templates'), $formOptions));
    }
}
