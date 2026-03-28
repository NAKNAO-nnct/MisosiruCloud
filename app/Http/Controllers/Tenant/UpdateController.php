<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function __invoke(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update([
            'name' => $request->name,
            'slug' => $request->slug,
        ]);

        return redirect()->route('tenants.show', $tenant)
            ->with('success', 'テナント情報を更新しました。');
    }
}
