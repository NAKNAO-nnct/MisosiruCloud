<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CreateTenantRequest;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class StoreController extends Controller
{
    public function __construct(private readonly TenantService $tenantService)
    {
    }

    public function __invoke(CreateTenantRequest $request): RedirectResponse
    {
        try {
            $tenant = $this->tenantService->create($request->validated());
        } catch (Throwable $e) {
            return redirect()->route('tenants.create')
                ->withInput()
                ->withErrors(['error' => 'テナント作成に失敗しました: ' . $e->getMessage()]);
        }

        return redirect()->route('tenants.show', $tenant->getId())
            ->with('success', 'テナントを作成しました。');
    }
}
