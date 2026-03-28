<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dbaas\CreateDatabaseRequest;
use App\Repositories\TenantRepository;
use App\Services\DbaasService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class StoreController extends Controller
{
    public function __construct(
        private readonly DbaasService $dbaasService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(CreateDatabaseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $tenant = $this->tenantRepository->findByIdOrFail((int) $validated['tenant_id']);
            $db = $this->dbaasService->provision($tenant, $validated);
        } catch (Throwable $e) {
            return redirect()->route('dbaas.create')
                ->withInput()
                ->withErrors(['error' => 'DBインスタンス作成に失敗しました: ' . $e->getMessage()]);
        }

        return redirect()->route('dbaas.show', $db->getId())
            ->with('success', 'DBインスタンスの作成を開始しました。');
    }
}
