<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(private readonly TenantRepository $tenantRepository)
    {
    }

    public function __invoke(Request $request): View
    {
        $tenants = $this->tenantRepository
            ->paginate($request->string('search')->toString() ?: null)
            ->withQueryString();

        return view('tenants.index', compact('tenants'));
    }
}
