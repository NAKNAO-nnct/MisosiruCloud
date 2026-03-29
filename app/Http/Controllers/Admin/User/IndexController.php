<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $users = $this->userRepository->allWithTenants();
        $tenants = $this->tenantRepository->all();

        return view('admin.users.index', compact('users', 'tenants'));
    }
}
