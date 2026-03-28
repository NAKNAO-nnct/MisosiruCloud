<?php

declare(strict_types=1);

namespace App\Http\Controllers\Container;

use App\Http\Controllers\Controller;
use App\Repositories\ContainerJobRepository;
use App\Services\ContainerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(
        private readonly ContainerService $containerService,
        private readonly ContainerJobRepository $containerJobRepository,
    ) {
    }

    public function __invoke(Request $request, int $container): RedirectResponse
    {
        $job = $this->containerJobRepository->findByIdOrFail($container);
        $this->containerService->terminateContainer($job);

        return redirect()->route('containers.index')->with('success', 'コンテナを削除しました。');
    }
}
