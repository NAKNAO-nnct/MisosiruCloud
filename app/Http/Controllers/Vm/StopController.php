<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StopController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(Request $request, int $vmid): RedirectResponse
    {
        $this->vmService->stopByVmid($vmid);

        return redirect()->route('vms.show', $vmid)->with('success', 'VMを停止しました。');
    }
}
