<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Repositories\TenantRepository;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __construct(private readonly TenantRepository $tenantRepository)
    {
    }

    public function __invoke(UpdateTenantRequest $request, int $tenant): RedirectResponse
    {
        $tenantData = $this->tenantRepository->update($tenant, [
            'name' => $request->name,
            'slug' => $request->slug,
        ]);

        return redirect()->route('tenants.show', $tenantData->getId())
            ->with('success', 'テナント情報を更新しました。');
    }
}
