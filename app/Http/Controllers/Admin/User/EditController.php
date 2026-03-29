<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request, int $id): View
    {
        $user = User::query()->with('tenants')->findOrFail($id);
        $tenants = $this->tenantRepository->all();
        $selectedTenantIds = $user->tenants->pluck('id')->map(fn (mixed $tenantId): int => (int) $tenantId)->all();

        return view('admin.users.edit', compact('user', 'tenants', 'selectedTenantIds'));
    }
}
