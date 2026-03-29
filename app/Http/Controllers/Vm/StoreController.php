<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Data\Vm\ProvisionVmCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vm\CreateVmRequest;
use App\Jobs\ProvisionVmJob;
use App\Repositories\TenantRepository;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly VmService $vmService,
    ) {
    }

    public function __invoke(CreateVmRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tenant = $this->tenantRepository->findByIdOrFail($request->integer('tenant_id'));

        $command = ProvisionVmCommand::make($validated);
        $vmMeta = $this->vmService->createVmMeta($tenant, $command);

        ProvisionVmJob::dispatch($vmMeta->getId(), [
            'template_vmid' => $command->getTemplateVmid(),
            'cpu' => $command->getCpu(),
            'memory_mb' => $command->getMemoryMb(),
            'disk_gb' => $command->getDiskGb(),
        ]);

        return redirect()->route('vms.show', $vmMeta->getProxmoxVmid())
            ->with('success', 'VMのプロビジョニングを開始しました。');
    }
}
