<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\VmMeta;
use App\Repositories\VmMetaRepository;
use Illuminate\View\View;

final class ProvisioningJobsController extends Controller
{
    public function __invoke(VmMetaRepository $vmMetaRepository): View
    {
        $jobs = VmMeta::query()
            ->with('tenant')
            ->orderByDesc('created_at')
            ->get();

        return view('vms.provisioning-jobs', compact('jobs'));
    }
}
