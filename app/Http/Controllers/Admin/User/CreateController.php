<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $tenants = $this->tenantRepository->all();

        return view('admin.users.create', compact('tenants'));
    }
}
