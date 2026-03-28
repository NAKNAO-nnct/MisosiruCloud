<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Services\VmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsoleController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(Request $request, int $vmid): View
    {
        $meta = $this->vmService->getVmWithMeta($vmid)['meta'];
        $vncProxy = $this->vmService->getVncProxyByVmid($vmid);

        return view('vms.console', compact('meta', 'vncProxy'));
    }
}
