<?php

declare(strict_types=1);

namespace App\Http\Controllers\Container;

use App\Http\Controllers\Controller;
use App\Repositories\ContainerJobRepository;
use App\Repositories\TenantRepository;
use App\Services\ContainerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly ContainerJobRepository $containerJobRepository,
        private readonly ContainerService $containerService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request, int $container): View
    {
        $job = $this->containerJobRepository->findByIdOrFail($container);
        $tenant = $this->tenantRepository->findByIdOrFail($job->getTenantId());

        $taskName = (string) ($request->query('task_name', 'app'));
        $logs = '';

        if ($request->has('task_name')) {
            $logs = $this->containerService->getLogs($job, $taskName);
        }

        return view('containers.show', [
            'job' => $job,
            'tenantName' => $tenant->getName(),
            'status' => $this->containerService->getContainerStatus($job),
            'allocations' => $this->containerService->getAllocations($job),
            'logs' => $logs,
            'taskName' => $taskName,
        ]);
    }
}
