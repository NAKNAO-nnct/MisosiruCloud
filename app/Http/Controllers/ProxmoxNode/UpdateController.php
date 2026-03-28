<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProxmoxNode;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProxmoxNode\SaveProxmoxNodeRequest;
use App\Repositories\ProxmoxNodeRepository;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __construct(private readonly ProxmoxNodeRepository $proxmoxNodeRepository)
    {
    }

    public function __invoke(SaveProxmoxNodeRequest $request, int $proxmoxNode): RedirectResponse
    {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'hostname' => $validated['hostname'],
            'api_token_id' => $validated['api_token_id'],
            'snippet_api_url' => $validated['snippet_api_url'],
        ];

        if (!empty($validated['api_token_secret'])) {
            $data['api_token_secret_encrypted'] = $validated['api_token_secret'];
        }

        if (!empty($validated['snippet_api_token'])) {
            $data['snippet_api_token_encrypted'] = $validated['snippet_api_token'];
        }

        $this->proxmoxNodeRepository->update($proxmoxNode, $data);

        return redirect()->route('proxmox-clusters.index')
            ->with('success', 'Proxmox クラスタ接続を更新しました。');
    }
}
