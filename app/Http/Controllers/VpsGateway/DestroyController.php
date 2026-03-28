<?php

declare(strict_types=1);

namespace App\Http\Controllers\VpsGateway;

use App\Http\Controllers\Controller;
use App\Repositories\VpsGatewayRepository;
use App\Services\VpsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(
        private readonly VpsGatewayRepository $vpsGatewayRepository,
        private readonly VpsGatewayService $vpsGatewayService,
    ) {
    }

    public function __invoke(Request $request, int $vpsGateway): RedirectResponse
    {
        $gateway = $this->vpsGatewayRepository->findByIdOrFail($vpsGateway);
        $this->vpsGatewayService->destroy($gateway);

        return redirect()->route('vps-gateways.index')
            ->with('success', 'VPS ゲートウェイを削除しました。');
    }
}
