<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Data\Vm\ProvisionVmCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vm\CreateVmRequest;
use App\Jobs\ProvisionVmJob;
use App\Repositories\TenantRepository;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(CreateVmRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tenant = $this->tenantRepository->findByIdOrFail($request->integer('tenant_id'));

        $command = ProvisionVmCommand::make($validated);
        ProvisionVmJob::dispatch($command);

        return redirect()->route('vms.index')->with('success', 'VMのプロビジョニングを開始しました。');
    }
}
