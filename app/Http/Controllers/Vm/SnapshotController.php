<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Services\VmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SnapshotController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(Request $request, int $vmid): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:64', 'alpha_dash']]);

        $this->vmService->createSnapshotByVmid($vmid, $request->string('name')->toString());

        return redirect()->route('vms.show', $vmid)->with('success', 'スナップショットを作成しました。');
    }
}
