<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Http\Controllers\Controller;
use App\Repositories\DatabaseInstanceRepository;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly DatabaseInstanceRepository $databaseInstanceRepository,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $databases = $this->databaseInstanceRepository->paginate(20)->withQueryString();
        $tenantNames = $this->tenantRepository->all()->mapWithKeys(fn ($tenant) => [$tenant->getId() => $tenant->getName()]);

        return view('dbaas.index', [
            'databases' => $databases,
            'tenantNames' => $tenantNames,
        ]);
    }
}
