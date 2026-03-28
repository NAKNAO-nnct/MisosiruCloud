<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vm\ResizeVmRequest;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\VmMeta;
use Illuminate\Http\RedirectResponse;

class ResizeController extends Controller
{
    public function __construct(private readonly ProxmoxApi $api)
    {
    }

    public function __invoke(ResizeVmRequest $request, int $vmid): RedirectResponse
    {
        $meta = VmMeta::where('proxmox_vmid', $vmid)->firstOrFail();
        $this->api->vm()->resizeVm(
            $meta->proxmox_node,
            $vmid,
            $request->string('disk')->toString(),
            $request->string('size')->toString(),
        );

        return redirect()->route('vms.show', $vmid)->with('success', 'ディスクをリサイズしました。');
    }
}
