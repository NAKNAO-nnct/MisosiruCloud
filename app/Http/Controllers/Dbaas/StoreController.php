<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Data\Dbaas\ProvisionDbaasCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dbaas\CreateDatabaseRequest;
use App\Jobs\ProvisionDbaasJob;
use App\Repositories\TenantRepository;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(CreateDatabaseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $tenant = $this->tenantRepository->findByIdOrFail((int) $validated['tenant_id']);
        $command = ProvisionDbaasCommand::make($validated);
        ProvisionDbaasJob::dispatch($command);

        return redirect()->route('dbaas.index')
            ->with('success', 'DBインスタンスの作成を開始しました。');
    }
}
