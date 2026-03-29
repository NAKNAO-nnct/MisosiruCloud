<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VmService;
use Illuminate\Http\JsonResponse;

class CheckVmidController extends Controller
{
    public function __construct(
        private readonly VmService $vmService,
    ) {
    }

    public function __invoke(int $vmid): JsonResponse
    {
        $node = $this->vmService->vmidExistsOnCluster($vmid);

        if ($node !== null) {
            return response()->json([
                'exists' => true,
                'node' => $node,
            ]);
        }

        return response()->json([
            'exists' => false,
        ]);
    }
}
