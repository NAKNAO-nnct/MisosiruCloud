<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Repositories\VmMetaRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

final class MetaDestroyController extends Controller
{
    public function __invoke(int $id, VmMetaRepository $vmMetaRepository): RedirectResponse|Redirector
    {
        $vmMetaRepository->forceDelete($id);

        return redirect()->route('vms.provisioning-jobs')->with('success', 'VM メタデータを削除しました。');
    }
}
