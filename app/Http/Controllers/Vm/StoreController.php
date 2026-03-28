<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vm\CreateVmRequest;
use App\Models\Tenant;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(CreateVmRequest $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->integer('tenant_id'));
        $this->vmService->provisionVm($tenant, $request->validated());

        return redirect()->route('vms.index')->with('success', 'VMのプロビジョニングを開始しました。');
    }
}
