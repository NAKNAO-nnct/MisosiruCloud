<?php

declare(strict_types=1);

namespace App\Http\Controllers\Container;

use App\Http\Controllers\Controller;
use App\Http\Requests\Container\DeployContainerRequest;
use App\Repositories\TenantRepository;
use App\Services\ContainerService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class StoreController extends Controller
{
    public function __construct(
        private readonly ContainerService $containerService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(DeployContainerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $tenant = $this->tenantRepository->findByIdOrFail((int) $validated['tenant_id']);
            $this->containerService->deployContainer($tenant, $validated);
        } catch (Throwable $e) {
            return redirect()->route('containers.create')
                ->withInput()
                ->withErrors(['error' => 'コンテナデプロイに失敗しました: ' . $e->getMessage()]);
        }

        return redirect()->route('containers.index')
            ->with('success', 'コンテナをデプロイしました。');
    }
}
