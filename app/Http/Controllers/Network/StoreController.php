<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\Network\StoreNetworkRequest;
use App\Services\NetworkService;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(private readonly NetworkService $networkService)
    {
    }

    public function __invoke(StoreNetworkRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $tenant = $this->networkService->createNetwork(
            tenantId: (int) $validated['tenant_id'],
            params: $validated,
        );

        return redirect()->route('networks.show', $tenant->getId())
            ->with('success', 'ネットワークを作成しました。');
    }
}
