<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use App\Repositories\VmMetaRepository;
use App\Services\NetworkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly VmMetaRepository $vmMetaRepository,
        private readonly NetworkService $networkService,
    ) {
    }

    public function __invoke(Request $request, int $tenant): View
    {
        $tenantData = $this->tenantRepository->findByIdOrFail($tenant);
        $vms = $this->vmMetaRepository->findByTenantId($tenantData->getId());

        $networkData = collect($this->networkService->listNetworks())
            ->first(fn ($network) => $network->getTenantId() === $tenantData->getId());
        $network = $networkData?->toArray();

        return view('networks.show', [
            'tenant' => $tenantData,
            'vms' => $vms,
            'network' => $network,
        ]);
    }
}
