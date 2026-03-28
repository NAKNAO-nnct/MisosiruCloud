<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): View
    {
        $tenant->load(['vmMetas', 'databaseInstances', 'containerJobs', 's3Credentials']);

        return view('tenants.show', compact('tenant'));
    }
}
