<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\ContainerJobRepository;
use App\Services\ContainerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContainerStatusController extends Controller
{
    public function __construct(
        private readonly ContainerJobRepository $containerJobRepository,
        private readonly ContainerService $containerService,
    ) {
    }

    public function __invoke(Request $request, int $container): JsonResponse
    {
        $job = $this->containerJobRepository->findByIdOrFail($container);
        $allocations = $this->containerService->getAllocations($job);

        return response()->json([
            'id' => $job->getId(),
            'nomad_job_id' => $job->getNomadJobId(),
            'status' => $this->containerService->getContainerStatus($job),
            'allocations_count' => count($allocations),
        ]);
    }
}
