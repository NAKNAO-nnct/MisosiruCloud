<?php

declare(strict_types=1);

namespace App\Http\Controllers\VpsGateway;

use App\Http\Controllers\Controller;
use App\Repositories\VpsGatewayRepository;
use App\Services\VpsGatewayService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __construct(
        private readonly VpsGatewayRepository $vpsGatewayRepository,
        private readonly VpsGatewayService $vpsGatewayService,
    ) {
    }

    public function __invoke(Request $request, int $vpsGateway): View
    {
        $gateway = $this->vpsGatewayRepository->findByIdOrFail($vpsGateway);

        return view('admin.vps.show', [
            'gateway' => $gateway,
            'wireguardConfig' => session('wireguard_conf') ?: $this->vpsGatewayService->generateWireguardConfig($gateway),
        ]);
    }
}
