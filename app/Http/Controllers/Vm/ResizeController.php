<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vm\ResizeVmRequest;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;

class ResizeController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(ResizeVmRequest $request, int $vmid): RedirectResponse
    {
        $this->vmService->resizeByVmid(
            $vmid,
            $request->string('disk')->toString(),
            $request->string('size')->toString(),
        );

        return redirect()->route('vms.show', $vmid)->with('success', 'ディスクをリサイズしました。');
    }
}
