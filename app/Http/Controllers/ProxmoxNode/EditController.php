<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProxmoxNode;

use App\Http\Controllers\Controller;
use App\Repositories\ProxmoxNodeRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditController extends Controller
{
    public function __construct(private readonly ProxmoxNodeRepository $proxmoxNodeRepository)
    {
    }

    public function __invoke(Request $request, int $proxmoxNode): View
    {
        $node = $this->proxmoxNodeRepository->findByIdOrFail($proxmoxNode);

        return view('proxmox-nodes.edit', compact('node'));
    }
}
