<?php

declare(strict_types=1);

namespace App\Http\Controllers\Container;

use App\Http\Controllers\Controller;
use App\Http\Requests\Container\ScaleContainerRequest;
use App\Repositories\ContainerJobRepository;
use App\Services\ContainerService;
use Illuminate\Http\RedirectResponse;

class ScaleController extends Controller
{
    public function __construct(
        private readonly ContainerService $containerService,
        private readonly ContainerJobRepository $containerJobRepository,
    ) {
    }

    public function __invoke(ScaleContainerRequest $request, int $container): RedirectResponse
    {
        $job = $this->containerJobRepository->findByIdOrFail($container);
        $this->containerService->scaleContainer($job, (int) $request->validated('replicas'));

        return redirect()->route('containers.show', $container)->with('success', 'レプリカ数を更新しました。');
    }
}
