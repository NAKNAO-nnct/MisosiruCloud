<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditController extends Controller
{
    public function __construct(private readonly TenantRepository $tenantRepository)
    {
    }

    public function __invoke(Request $request, int $tenant): View
    {
        $tenant = $this->tenantRepository->findByIdOrFail($tenant);

        return view('tenants.edit', compact('tenant'));
    }
}
