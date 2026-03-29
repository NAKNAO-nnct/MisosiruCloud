<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Jobs\DestroyVmJob;
use App\Repositories\VmMetaRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(
        private readonly VmMetaRepository $vmMetaRepository,
    ) {
    }

    public function __invoke(Request $request, int $vmid): RedirectResponse
    {
        $meta = $this->vmMetaRepository->findByVmidOrFail($vmid);
        DestroyVmJob::dispatch($meta->getId());

        return redirect()->route('vms.index')->with('success', 'VM削除ジョブを開始しました。');
    }
}
