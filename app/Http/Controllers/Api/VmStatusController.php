<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\VmMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VmStatusController extends Controller
{
    public function __construct(private readonly ProxmoxApi $api)
    {
    }

    public function __invoke(Request $request, int $vmid): JsonResponse
    {
        $meta = VmMeta::where('proxmox_vmid', $vmid)->firstOrFail();
        $status = $this->api->vm()->getVmStatus($meta->proxmox_node, $vmid);

        return response()->json($status);
    }
}
