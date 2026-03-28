<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\VmMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StartController extends Controller
{
    public function __construct(private readonly ProxmoxApi $api)
    {
    }

    public function __invoke(Request $request, int $vmid): RedirectResponse
    {
        $meta = VmMeta::where('proxmox_vmid', $vmid)->firstOrFail();
        $this->api->vm()->startVm($meta->proxmox_node, $vmid);

        return redirect()->route('vms.show', $vmid)->with('success', 'VMを起動しました。');
    }
}
