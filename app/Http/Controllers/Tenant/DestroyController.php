<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DestroyController extends Controller
{
    public function __construct(private readonly TenantService $tenantService)
    {
    }

    public function __invoke(Request $request, int $tenant): RedirectResponse
    {
        $this->tenantService->delete($tenant);

        return redirect()->route('tenants.index')
            ->with('success', 'テナントを削除しました。');
    }
}
