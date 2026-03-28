<?php

declare(strict_types=1);

namespace App\Http\Controllers\VpsGateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\VpsGateway\SaveVpsGatewayRequest;
use App\Services\VpsGatewayService;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(private readonly VpsGatewayService $vpsGatewayService)
    {
    }

    public function __invoke(SaveVpsGatewayRequest $request): RedirectResponse
    {
        $gateway = $this->vpsGatewayService->register($request->validated());
        $wireguardConfig = $this->vpsGatewayService->generateWireguardConfig($gateway);

        return redirect()
            ->route('vps-gateways.show', $gateway->getId())
            ->with('success', 'VPS ゲートウェイを登録しました。')
            ->with('wireguard_conf', $wireguardConfig);
    }
}
