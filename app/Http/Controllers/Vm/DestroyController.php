<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\VmMeta;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(Request $request, int $vmid): RedirectResponse
    {
        $meta = VmMeta::where('proxmox_vmid', $vmid)->firstOrFail();
        $this->vmService->terminateVm($meta);

        return redirect()->route('vms.index')->with('success', 'VMを削除しました。');
    }
}
