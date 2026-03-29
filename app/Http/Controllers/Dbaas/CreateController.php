<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dbaas;

use App\Enums\DatabaseType;
use App\Http\Controllers\Controller;
use App\Repositories\TenantRepository;
use App\Services\VmService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateController extends Controller
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
        private readonly VmService $vmService,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $formOptions = $this->vmService->getFormOptions();

        $dbVersions = collect(DatabaseType::cases())
            ->mapWithKeys(fn (DatabaseType $type) => [$type->value => $type->versions()])
            ->all();

        return view('dbaas.create', array_merge([
            'tenants' => $this->tenantRepository->all(),
            'dbTypes' => DatabaseType::cases(),
            'dbVersions' => $dbVersions,
            'templates' => $this->vmService->listAllVms(),
        ], $formOptions));
    }
}
