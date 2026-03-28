<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApi;
use App\Repositories\VmMetaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VmStatusController extends Controller
{
    public function __construct(
        private readonly ProxmoxApi $api,
        private readonly VmMetaRepository $vmMetaRepository,
    ) {
    }

    public function __invoke(Request $request, int $vmid): JsonResponse
    {
        $meta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        $status = $this->api->vm()->getVmStatus($meta->getProxmoxNode(), $vmid);

        return response()->json($status);
    }
}
