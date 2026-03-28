<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\VmMeta;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsoleController extends Controller
{
    public function __construct(private readonly ProxmoxApi $api)
    {
    }

    public function __invoke(Request $request, int $vmid): View
    {
        $meta = VmMeta::where('proxmox_vmid', $vmid)->with('tenant')->firstOrFail();
        $vncProxy = $this->api->vm()->getVncProxy($meta->proxmox_node, $vmid);

        return view('vms.console', compact('meta', 'vncProxy'));
    }
}
