<?php

declare(strict_types=1);

namespace App\Http\Controllers\Container;

use App\Http\Controllers\Controller;
use App\Http\Requests\Container\GetContainerLogsRequest;
use App\Repositories\ContainerJobRepository;
use App\Services\ContainerService;
use Illuminate\Http\Response;

class LogsController extends Controller
{
    public function __construct(
        private readonly ContainerService $containerService,
        private readonly ContainerJobRepository $containerJobRepository,
    ) {
    }

    public function __invoke(GetContainerLogsRequest $request, int $container): Response
    {
        $job = $this->containerJobRepository->findByIdOrFail($container);
        $taskName = (string) $request->validated('task_name');
        $logs = $this->containerService->getLogs($job, $taskName);

        return response($logs, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
