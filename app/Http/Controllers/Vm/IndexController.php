<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Repositories\VmMetaRepository;
use App\Services\VmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly VmService $vmService,
        private readonly VmMetaRepository $vmMetaRepository,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $vms = $this->vmService->listAllVms();
        $metas = $this->vmMetaRepository->allWithTenant();

        return view('vms.index', compact('vms', 'metas'));
    }
}
