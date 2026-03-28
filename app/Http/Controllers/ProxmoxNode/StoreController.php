<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProxmoxNode;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProxmoxNode\SaveProxmoxNodeRequest;
use App\Repositories\ProxmoxNodeRepository;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(private readonly ProxmoxNodeRepository $proxmoxNodeRepository)
    {
    }

    public function __invoke(SaveProxmoxNodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->proxmoxNodeRepository->create([
            'name' => $validated['name'],
            'hostname' => $validated['hostname'],
            'api_token_id' => $validated['api_token_id'],
            'api_token_secret_encrypted' => $validated['api_token_secret'],
            'snippet_api_url' => $validated['snippet_api_url'],
            'snippet_api_token_encrypted' => $validated['snippet_api_token'],
            'is_active' => false,
        ]);

        return redirect()->route('proxmox-clusters.index')
            ->with('success', 'Proxmox クラスタ接続を登録しました。');
    }
}
