<?php

declare(strict_types=1);

namespace App\Http\Controllers\VpsGateway;

use App\Http\Controllers\Controller;
use App\Repositories\VpsGatewayRepository;
use App\Services\VpsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private readonly VpsGatewayRepository $vpsGatewayRepository,
        private readonly VpsGatewayService $vpsGatewayService,
    ) {
    }

    public function __invoke(Request $request, int $vpsGateway): RedirectResponse
    {
        $gateway = $this->vpsGatewayRepository->findByIdOrFail($vpsGateway);
        $result = $this->vpsGatewayService->sync($gateway);

        return redirect()->route('vps-gateways.show', $vpsGateway)
            ->with('success', 'VPS 接続状態を同期しました。')
            ->with('sync_result', $result);
    }
}
