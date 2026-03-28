<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProxmoxNode;

use App\Http\Controllers\Controller;
use App\Repositories\ProxmoxNodeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivateController extends Controller
{
    public function __construct(private readonly ProxmoxNodeRepository $proxmoxNodeRepository)
    {
    }

    public function __invoke(Request $request, int $proxmoxNode): RedirectResponse
    {
        $this->proxmoxNodeRepository->activate($proxmoxNode);

        return redirect()->route('proxmox-clusters.index')
            ->with('success', 'Proxmox クラスタ接続を有効化しました。');
    }
}
