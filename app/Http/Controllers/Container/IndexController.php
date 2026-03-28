<?php

declare(strict_types=1);

namespace App\Http\Controllers\Container;

use App\Http\Controllers\Controller;
use App\Repositories\ContainerJobRepository;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly ContainerJobRepository $containerJobRepository,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $jobs = $this->containerJobRepository->paginate(20)->withQueryString();
        $tenantNames = $this->tenantRepository->all()->mapWithKeys(fn ($tenant) => [$tenant->getId() => $tenant->getName()]);

        return view('containers.index', [
            'jobs' => $jobs,
            'tenantNames' => $tenantNames,
        ]);
    }
}
