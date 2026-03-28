<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vm\CreateVmRequest;
use App\Repositories\TenantRepository;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly VmService $vmService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(CreateVmRequest $request): RedirectResponse
    {
        $tenant = $this->tenantRepository->findByIdOrFail($request->integer('tenant_id'));
        $this->vmService->provisionVm($tenant, $request->validated());

        return redirect()->route('vms.index')->with('success', 'VMのプロビジョニングを開始しました。');
    }
}
