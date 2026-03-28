<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\VmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateController extends Controller
{
    public function __construct(private readonly VmService $vmService)
    {
    }

    public function __invoke(Request $request): View
    {
        $tenants = Tenant::all();
        $templates = $this->vmService->listAllVms();

        return view('vms.create', compact('tenants', 'templates'));
    }
}
