<?php

declare(strict_types=1);

namespace App\Http\Controllers\VpsGateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\VpsGateway\SaveVpsGatewayRequest;
use App\Repositories\VpsGatewayRepository;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __construct(private readonly VpsGatewayRepository $vpsGatewayRepository)
    {
    }

    public function __invoke(SaveVpsGatewayRequest $request, int $vpsGateway): RedirectResponse
    {
        $validated = $request->validated();

        $this->vpsGatewayRepository->update($vpsGateway, [
            'name' => (string) $validated['name'],
            'global_ip' => (string) $validated['global_ip'],
            'wireguard_port' => isset($validated['wireguard_port']) ? (int) $validated['wireguard_port'] : 51820,
            'wireguard_public_key' => (string) $validated['wireguard_public_key'],
            'status' => (string) $validated['status'],
            'purpose' => isset($validated['purpose']) ? (string) $validated['purpose'] : null,
        ]);

        return redirect()->route('vps-gateways.show', $vpsGateway)
            ->with('success', 'VPS ゲートウェイを更新しました。');
    }
}
